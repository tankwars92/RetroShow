import json
import os
import shlex
import sqlite3
import subprocess
import sys
import threading
import time
from datetime import datetime, timezone
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from typing import Optional


BASE_DIR = os.path.dirname(os.path.abspath(__file__))

ROOT_DIR = os.path.abspath(os.path.join(BASE_DIR, ".."))
DB_PATH = os.path.join(ROOT_DIR, "retroshow.sqlite")
UPLOADS_DIR = os.path.join(ROOT_DIR, "uploads")
FFMPEG_BIN = "ffmpeg"
FFPROBE_BIN = "ffprobe"
HOST = "127.0.0.1"
PORT = 8090
LOG_PATH = "log.txt"


def log_line(msg: str):
    line = f"[{datetime.now().strftime('%Y-%m-%d %H:%M:%S')}] {msg}"
    try:
        sys.stderr.write(line + "\n")
        sys.stderr.flush()
    except Exception:
        pass
    try:
        os.makedirs(UPLOADS_DIR, exist_ok=True)
        with open(LOG_PATH, "a", encoding="utf-8") as f:
            f.write(line + "\n")
    except Exception:
        pass


def log_progress_tty(label: str, percent: int, width: int = 24):
    bar = progress_bar(percent, width=width)
    line = f"[{datetime.now().strftime('%Y-%m-%d %H:%M:%S')}] {label}: {bar}"
    try:
        use_cr = sys.stderr.isatty()
    except Exception:
        use_cr = False
    try:
        if use_cr:
            pad = 8
            sys.stderr.write("\r" + line + (" " * pad))
            if percent >= 100:
                sys.stderr.write("\n")
        else:
            sys.stderr.write(line + "\n")
        sys.stderr.flush()
    except Exception:
        pass


def progress_bar(percent: int, width: int = 24):
    p = max(0, min(100, int(percent)))
    filled = int((p / 100.0) * width)
    return "[" + ("#" * filled) + ("-" * (width - filled)) + f"] {p:3d}%"


class QueueWorker:
    def __init__(self, db_path: str):
        self.db_path = db_path
        self._lock = threading.Lock()

    def db(self):
        con = sqlite3.connect(self.db_path, timeout=30)
        con.row_factory = sqlite3.Row
        return con

    def _set_queue_status(
        self,
        con: sqlite3.Connection,
        queue_id: int,
        status: str,
        last_error: str = "",
        started: bool = False,
        finished: bool = False,
    ):
        now = int(time.time())
        if started:
            con.execute(
                "UPDATE video_processing_queue SET status=?, started_at=?, attempts=attempts+1, last_error=? WHERE id=?",
                (status, now, last_error, queue_id),
            )
        elif finished:
            con.execute(
                "UPDATE video_processing_queue SET status=?, finished_at=?, last_error=? WHERE id=?",
                (status, now, last_error, queue_id),
            )
        else:
            con.execute(
                "UPDATE video_processing_queue SET status=?, last_error=? WHERE id=?",
                (status, last_error, queue_id),
            )

    def _ffmpeg_convert(self, source_abs: str, final_abs: str):
        has_video = self._has_video_stream(source_abs)
        duration = self._media_duration_seconds(source_abs)
        if has_video:
            cmd = [
                FFMPEG_BIN,
                "-hide_banner",
                "-progress",
                "pipe:1",
                "-nostats",
                "-i",
                source_abs,
                "-vsync",
                "cfr",
                "-r",
                "30",
                "-c:v",
                "libx264",
                "-profile:v",
                "baseline",
                "-level",
                "3.0",
                "-crf",
                "25",
                "-preset",
                "veryfast",
                "-c:a",
                "aac",
                "-b:a",
                "96k",
                "-ar",
                "44100",
                "-ac",
                "2",
                "-movflags",
                "+faststart",
                "-brand",
                "mp42",
                "-y",
                final_abs,
            ]
        else:
            cmd = [
                FFMPEG_BIN,
                "-hide_banner",
                "-progress",
                "pipe:1",
                "-nostats",
                "-i",
                source_abs,
                "-f",
                "lavfi",
                "-i",
                "color=c=black:s=640x360",
                "-shortest",
                "-vsync",
                "cfr",
                "-r",
                "30",
                "-c:v",
                "libx264",
                "-profile:v",
                "baseline",
                "-level",
                "3.0",
                "-crf",
                "25",
                "-preset",
                "veryfast",
                "-c:a",
                "aac",
                "-b:a",
                "96k",
                "-ar",
                "44100",
                "-ac",
                "2",
                "-movflags",
                "+faststart",
                "-brand",
                "mp42",
                "-y",
                final_abs,
            ]
        self._run_ffmpeg_with_progress(cmd, duration, "Converting")

    def _ffmpeg_preview(self, final_abs: str, preview_abs: str):
        has_video = self._has_video_stream(final_abs)
        if has_video:
            cmd = [FFMPEG_BIN, "-i", final_abs, "-ss", "00:00:01", "-vframes", "1", "-y", preview_abs]
        else:
            cmd = [FFMPEG_BIN, "-f", "lavfi", "-i", "color=c=black:s=640x360", "-vframes", "1", "-y", preview_abs]
        proc = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
        if proc.returncode != 0:
            raise RuntimeError(proc.stderr.strip()[:4000] or "FFmpeg preview failed.")

    def _media_duration_seconds(self, path: str):
        cmd = [
            FFPROBE_BIN,
            "-v",
            "error",
            "-show_entries",
            "format=duration",
            "-of",
            "default=noprint_wrappers=1:nokey=1",
            path,
        ]
        proc = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
        if proc.returncode != 0:
            return 0.0
        try:
            return max(0.0, float(proc.stdout.strip() or "0"))
        except Exception:
            return 0.0

    def _run_ffmpeg_with_progress(self, cmd: list[str], duration: float, label: str):
        proc = subprocess.Popen(
            cmd,
            stdout=subprocess.PIPE,
            stderr=subprocess.STDOUT,  # ВАЖНО
            text=True,
            bufsize=1,
            universal_newlines=True,
        )

        last_logged = -1
        while True:
            line = proc.stdout.readline() if proc.stdout is not None else ""
            if not line:
                if proc.poll() is not None:
                    break
                continue
            line = line.strip()

            out_us = None

            if line.startswith("out_time_us="):
                try:
                    out_us = int(line.split("=", 1)[1])
                except Exception:
                    out_us = None

            elif line.startswith("out_time_ms="):
                try:
                    out_us = int(line.split("=", 1)[1])
                except Exception:
                    out_us = None

            if out_us is not None and duration > 0:
                try:
                    percent = int((out_us / (duration * 1_000_000.0)) * 100)
                    percent = max(0, min(100, percent))
                    if percent != last_logged:
                        last_logged = percent
                        log_progress_tty(label, percent)
                        try:
                            os.makedirs(UPLOADS_DIR, exist_ok=True)
                            with open(LOG_PATH, "a", encoding="utf-8") as f:
                                f.write(
                                    f"[{datetime.now().strftime('%Y-%m-%d %H:%M:%S')}] {label}: {progress_bar(percent)}\n"
                                )
                                
                        except Exception:
                            pass

                except Exception:
                    pass
                    
            elif line == "progress=end":
                if last_logged < 100:
                    log_progress_tty(label, 100)
                    try:
                        os.makedirs(UPLOADS_DIR, exist_ok=True)
                        with open(LOG_PATH, "a", encoding="utf-8") as f:
                            f.write(
                                f"[{datetime.now().strftime('%Y-%m-%d %H:%M:%S')}] {label}: {progress_bar(100)}\n"
                            )
                    except Exception:
                        pass

        stderr_data = proc.stderr.read() if proc.stderr is not None else ""
        rc = proc.wait()
        if rc != 0:
            raise RuntimeError(stderr_data.strip()[:4000] or f"{label} failed.")

    def _has_video_stream(self, path: str):
        cmd = [
            FFPROBE_BIN,
            "-v",
            "error",
            "-select_streams",
            "v:0",
            "-show_entries",
            "stream=codec_type",
            "-of",
            "default=noprint_wrappers=1:nokey=1",
            path,
        ]
        proc = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
        if proc.returncode != 0:
            return False
        return proc.stdout.strip() == "video"

    def process_one(self, queue_id: int):
        with self._lock:
            con = self.db()
            try:
                row = con.execute(
                    "SELECT * FROM video_processing_queue WHERE id = ? LIMIT 1",
                    (queue_id,),
                ).fetchone()
                if row is None:
                    return False
                if row["status"] == "failed" and int(row["attempts"] or 0) >= 3:
                    return False
                if row["status"] not in ("pending", "failed"):
                    return False

                log_line(
                    f"Queue {queue_id} accepted: public_id: {row['public_id']}, user: {row['user']}, source: {row['source_file']}."
                )
                self._set_queue_status(con, queue_id, "processing", started=True)
                con.commit()

                source_rel = str(row["source_file"])
                source_abs = os.path.normpath(os.path.join(ROOT_DIR, source_rel))
                if not source_abs.startswith(ROOT_DIR) or not os.path.isfile(source_abs):
                    raise RuntimeError("Source file not found.")

                created_at = int(row["created_at"] or time.time())
                created = datetime.fromtimestamp(created_at, tz=timezone.utc).strftime(
                    "%Y-%m-%dT%H:%M:%SZ"
                )
                is_private = 1 if str(row["broadcast"]) == "private" else 0

                cur = con.execute(
                    """
                    INSERT INTO videos (public_id, title, description, file, preview, user, time, private, tags)
                    VALUES (?, ?, ?, '', '', ?, ?, ?, ?)
                    """,
                    (
                        str(row["public_id"]),
                        str(row["title"]),
                        str(row["description"]),
                        str(row["user"]),
                        created,
                        is_private,
                        str(row["tags"]),
                    ),
                )
                video_id = int(cur.lastrowid)
                file_base = f"{video_id}_{row['public_id']}"
                final_rel = f"uploads/{file_base}.mp4"
                preview_rel = f"uploads/{file_base}_preview.jpg"
                final_abs = os.path.join(ROOT_DIR, final_rel)
                preview_abs = os.path.join(ROOT_DIR, preview_rel)

                os.makedirs(UPLOADS_DIR, exist_ok=True)
                log_line(f"Queue {queue_id} started converting.")
                self._ffmpeg_convert(source_abs, final_abs)
                log_line(f"Queue {queue_id} started making preview.")
                self._ffmpeg_preview(final_abs, preview_abs)

                con.execute(
                    "UPDATE videos SET file = ?, preview = ? WHERE id = ?",
                    (final_rel, preview_rel, video_id),
                )
                self._set_queue_status(con, queue_id, "done", finished=True)
                con.commit()
                log_line(f"Queue {queue_id} completed successfully.")

                try:
                    os.remove(source_abs)
                except OSError:
                    pass
                return True
            except Exception as exc:
                msg = str(exc)[:4000]
                log_line(f"Queue {queue_id} failed: {msg}")
                try:
                    con.execute(
                        "DELETE FROM videos WHERE public_id = ? AND file = ''",
                        (str(row["public_id"]) if row is not None else "",),
                    )
                except Exception:
                    pass
                try:
                    self._set_queue_status(con, queue_id, "failed", last_error=msg, finished=True)
                    con.commit()
                except Exception:
                    pass
                return False
            finally:
                con.close()

    def process_pending(self, limit: int = 3):
        con = self.db()
        try:
            rows = con.execute(
                "SELECT id FROM video_processing_queue WHERE status = 'pending' OR (status = 'failed' AND attempts < 3) ORDER BY created_at ASC LIMIT ?",
                (limit,),
            ).fetchall()
        finally:
            con.close()
        done = 0
        for r in rows:
            if self.process_one(int(r["id"])):
                done += 1
        return done


worker = QueueWorker(DB_PATH)


def run_background_loop():
    while True:
        try:
            worker.process_pending(limit=5)
        except Exception:
            pass
        time.sleep(2.0)


class Handler(BaseHTTPRequestHandler):
    def _send(self, code: int, data: dict):
        body = json.dumps(data, ensure_ascii=False).encode("utf-8")
        self.send_response(code)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def do_GET(self):
        if self.path.startswith("/health"):
            self._send(200, {"ok": True})
            return
        self._send(404, {"ok": False, "error": "not_found"})

    def do_POST(self):
        if not self.path.startswith("/queue"):
            self._send(404, {"ok": False, "error": "not_found"})
            return
        try:
            peer = self.client_address[0] if self.client_address else "?"
        except Exception:
            peer = "?"
        log_line(f"Received POST request from {peer} on {self.path}.")
        length = int(self.headers.get("Content-Length", "0") or "0")
        raw = self.rfile.read(length) if length > 0 else b""
        queue_id: Optional[int] = None
        if raw:
            try:
                payload = json.loads(raw.decode("utf-8"))
                queue_id = int(payload.get("queue_id", 0)) or None
            except Exception:
                queue_id = None

        if queue_id is not None:
            log_line(f"Received queue_id: {queue_id}.")
            threading.Thread(target=worker.process_one, args=(queue_id,), daemon=True).start()
            self._send(200, {"ok": True, "queued": queue_id})
        else:
            log_line("Triggering pending queue processing.")
            threading.Thread(target=worker.process_pending, daemon=True).start()
            self._send(200, {"ok": True, "queued": "pending"})

    def log_message(self, fmt, *args):
        return


if __name__ == "__main__":
    try:
        sys.stderr.reconfigure(encoding="utf-8", errors="replace")
        sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    except Exception:
        pass
    threading.Thread(target=run_background_loop, daemon=True).start()
    server = ThreadingHTTPServer((HOST, PORT), Handler)
    log_line(f"Working on http://{HOST}:{PORT} (log file: {LOG_PATH}).")
    server.serve_forever()

