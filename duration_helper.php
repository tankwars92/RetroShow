<?php
static $duration_cache = array();

function get_video_duration_fast($file, $id) {
    global $duration_cache;
    
    if (isset($duration_cache[$id])) {
        return $duration_cache[$id];
    }
    $cache_file = __DIR__ . '/uploads/' . intval($id) . '_duration.txt';
    
    if (file_exists($cache_file)) {
        $duration = trim(file_get_contents($cache_file));
        if (preg_match('/^\d{1,5}(:[0-5]\d){1,2}$/', $duration)) {
            $duration_cache[$id] = $duration;
            return $duration;
        }
    }
    
    $ffprobe = 'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 ' . escapeshellarg($file);
    
    $descriptorspec = array(
        0 => array("pipe", "r"),
        1 => array("pipe", "w"),
        2 => array("pipe", "w")
    );
    
    $process = proc_open($ffprobe, $descriptorspec, $pipes);
    
    if (is_resource($process)) {
        stream_set_blocking($pipes[1], 0);
        stream_set_blocking($pipes[2], 0);
        
        $start_time = time();
        $timeout = 2;
        $output = '';
        
        do {
            $status = proc_get_status($process);
            
            if (!$status['running']) {
                break;
            }
            
            $output .= stream_get_contents($pipes[1]);
            
            if (time() - $start_time > $timeout) {
                proc_terminate($process);
                break;
            }
            
            usleep(100000);
        } while (true);
        
        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        
        if (is_numeric(trim($output))) {
            $seconds = intval(round(floatval(trim($output))));
            $h = floor($seconds / 3600);
            $m = floor(($seconds % 3600) / 60);
            $s = $seconds % 60;
            
            if ($h > 0) {
                $duration = sprintf('%d:%02d:%02d', $h, $m, $s);
            } else {
                $duration = sprintf('%d:%02d', $m, $s);
            }
            
            if ($seconds < 360000) {
                file_put_contents($cache_file, $duration);
            }
            
            $duration_cache[$id] = $duration;
            
            return $duration;
        }
    }
    
    $duration_cache[$id] = '--:--';
    return '--:--';
}

function start_duration_background($file, $id) {
    $cache_file = __DIR__ . '/uploads/' . intval($id) . '_duration.txt';
    
    if (file_exists($cache_file)) {
        return;
    }
    
    $lock_file = __DIR__ . '/uploads/' . intval($id) . '_duration.lock';
    if (file_exists($lock_file)) {
        $lock_time = filemtime($lock_file);
        if (time() - $lock_time < 30) {
            return;
        }
        unlink($lock_file);
    }
    
    file_put_contents($lock_file, time());
    
    $ffprobe = 'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 ' . escapeshellarg($file);
    $output_file = __DIR__ . '/uploads/' . intval($id) . '_duration_temp.txt';
    
    $command = $ffprobe . ' > ' . escapeshellarg($output_file) . ' 2>/dev/null && ';
    $command .= 'if [ -f ' . escapeshellarg($output_file) . ' ]; then ';
    $command .= 'duration=$(cat ' . escapeshellarg($output_file) . '); ';
    $command .= 'if [ -n "$duration" ] && [ "$duration" != "N/A" ]; then ';
    $command .= 'echo "$duration" > ' . escapeshellarg($cache_file) . '; ';
    $command .= 'fi; ';
    $command .= 'rm -f ' . escapeshellarg($output_file) . '; ';
    $command .= 'fi; ';
    $command .= 'rm -f ' . escapeshellarg($lock_file) . ' &';
    
    exec($command);
} 