<?php

if (!defined('SMTP_HOST')) {
    die('Mailer: SMTP config is not defined. Make sure config.php is included.');
}

function send_smtp_email_advanced($to_email, $to_name, $subject, $body, $is_html = true) {
    $result = send_smtp_native($to_email, $to_name, $subject, $body, $is_html);
    if (!$result) {
        $result = send_smtp_email($to_email, $to_name, $subject, $body, $is_html);
    }
    return $result;
}

function send_smtp_native($to_email, $to_name, $subject, $body, $is_html = true) {
    $errno = 0;
    $errstr = '';

    $boundary = md5(uniqid(time()));
    $eol = "\r\n";

    $from_email = SMTP_FROM_EMAIL;
    $from_name  = SMTP_FROM_NAME;

    $headers = "Date: ".date("r")."$eol";
    $headers .= "From: =?UTF-8?B?".base64_encode($from_name)."?= <$from_email>$eol";
    $headers .= "Reply-To: $from_email$eol";
    $headers .= "Subject: =?UTF-8?B?".base64_encode($subject)."?=$eol";
    $headers .= "Message-ID: <".md5(uniqid(time()))."@$from_email>$eol";
    $headers .= "X-Mailer: PHP v".phpversion()."$eol";
    $headers .= "MIME-Version: 1.0$eol";

    if ($is_html) {
        $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"$eol";
        $message = "--$boundary$eol";
        $message .= "Content-Type: text/plain; charset=UTF-8$eol";
        $message .= "Content-Transfer-Encoding: base64$eol\r\n";
        $message .= chunk_split(base64_encode(strip_tags($body)))."$eol";
        $message .= "--$boundary$eol";
        $message .= "Content-Type: text/html; charset=UTF-8$eol";
        $message .= "Content-Transfer-Encoding: base64$eol\r\n";
        $message .= chunk_split(base64_encode($body))."$eol";
        $message .= "--$boundary--$eol";
    } else {
        $headers .= "Content-Type: text/plain; charset=UTF-8$eol";
        $headers .= "Content-Transfer-Encoding: base64$eol\r\n";
        $message = chunk_split(base64_encode($body));
    }

    if (SMTP_PORT == 465 || SMTP_SECURE === 'ssl') {
        $host = "ssl://".SMTP_HOST;
    } else {
        $host = SMTP_HOST;
    }

    $smtp_conn = @fsockopen($host, SMTP_PORT, $errno, $errstr, 30);
    if (!$smtp_conn) {
        return false;
    }

    stream_set_timeout($smtp_conn, 30);

    $response = fgets($smtp_conn, 515);
    if (substr($response, 0, 3) != '220') {
        fclose($smtp_conn);
        return false;
    }

    fputs($smtp_conn, "EHLO ".gethostname()."$eol");
    $ehloLines = [];
    while (($line = fgets($smtp_conn, 515)) !== false) {
        $ehloLines[] = trim($line);
        if (substr($line, 0, 4) === '250 ') {
            break;
        }
    }

    fputs($smtp_conn, "AUTH LOGIN$eol");
    $response = fgets($smtp_conn, 515);
    if (substr($response, 0, 3) != '334') {
        fclose($smtp_conn);
        return false;
    }

    fputs($smtp_conn, base64_encode(SMTP_USERNAME)."$eol");
    $response = fgets($smtp_conn, 515);
    if (substr($response, 0, 3) != '334') {
        fclose($smtp_conn);
        return false;
    }

    fputs($smtp_conn, base64_encode(SMTP_PASSWORD)."$eol");
    $response = fgets($smtp_conn, 515);
    if (substr($response, 0, 3) != '235') {
        fclose($smtp_conn);
        return false;
    }

    fputs($smtp_conn, "MAIL FROM: <".$from_email.">$eol");
    $response = fgets($smtp_conn, 515);
    if (substr($response, 0, 3) != '250') {
        fclose($smtp_conn);
        return false;
    }

    fputs($smtp_conn, "RCPT TO: <".$to_email.">$eol");
    $response = fgets($smtp_conn, 515);
    if (substr($response, 0, 3) != '250') {
        fclose($smtp_conn);
        return false;
    }

    fputs($smtp_conn, "DATA$eol");
    $response = fgets($smtp_conn, 515);
    if (substr($response, 0, 3) != '354') {
        fclose($smtp_conn);
        return false;
    }

    fputs($smtp_conn, $headers."\r\n");
    fputs($smtp_conn, $message."\r\n");
    fputs($smtp_conn, ".$eol");
    $response = fgets($smtp_conn, 515);

    fputs($smtp_conn, "QUIT$eol");
    fclose($smtp_conn);

    $ok = substr($response, 0, 3) == '250';
    return $ok;
}

function send_smtp_email($to_email, $to_name, $subject, $body, $is_html = true) {
    $headers = array();
    $headers[] = "MIME-Version: 1.0";
    $headers[] = $is_html ? "Content-type: text/html; charset=UTF-8" : "Content-type: text/plain; charset=UTF-8";
    $headers[] = "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">";
    $headers[] = "Reply-To: " . SMTP_FROM_EMAIL;
    $headers[] = "X-Mailer: PHP/" . phpversion();

    $header_string = implode("\r\n", $headers);

    return mail($to_email, $subject, $body, $header_string);
}

