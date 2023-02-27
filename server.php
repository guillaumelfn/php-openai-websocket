<?php

/* PHP websocket server, php server.php to start */

/* server listen on IP and port */

$host = 'localhost'; //IP for listening
$port = 9000; // port

/* send message */

function send_message($msg,$client) {
        @socket_write($client, $msg, strlen($msg));
}

/* masking text */

function mask($text) {
    $b1 = 0x81;
    $length = strlen($text);

    if ($length <= 125)
        $header = pack('CC', $b1, $length);
    elseif ($length > 125 && $length < 65536)
        $header = pack('CCn', $b1, 126, $length);
    elseif ($length >= 65536)
        $header = pack('CCNN', $b1, 127, $length);

    return $header . $text;
}

/* unmasking text */

function unmask($text) {
    $length = ord($text[1]) & 127;
    if ($length == 126) {
        $masks = substr($text, 4, 4);
        $data = substr($text, 8);
    } elseif ($length == 127) {
        $masks = substr($text, 10, 4);
        $data = substr($text, 14);
    } else {
        $masks = substr($text, 2, 4);
        $data = substr($text, 6);
    }

    $text = "";
    for ($i = 0; $i < strlen($data); ++$i) {
        $text .= $data[$i] ^ $masks[$i % 4];
    }

    return $text;
}

/* handshaking */

function perform_handshaking($receved_header, $client_conn, $host, $port) {
    $headers = array();
    $lines = preg_split("/\r\n/", $receved_header);
    foreach ($lines as $line) {
        $line = chop($line);
        if (preg_match('/\A(\S+): (.*)\z/', $line, $matches)) {
            $headers[$matches[1]] = $matches[2];
        }
    }

    $secKey = $headers['Sec-WebSocket-Key'];
    $secAccept = base64_encode(pack('H*', sha1($secKey . "258EAFA5-E914-47DA-95CA-C5AB0DC85B11")));
    $upgrade  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
                "Upgrade: websocket\r\n" .
                "Connection: Upgrade\r\n" .
                "Sec-WebSocket-Accept:$secAccept\r\n\r\n";
    socket_write($client_conn, $upgrade, strlen($upgrade));
}
/* Web socket - server */

/* Add OpenAI of course */

require_once 'OpenAI.php';

$openai = New OpenAI();

/* multiple clients will connect - so these are the prompts */

$prompt=Array();

$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_bind($socket, $host, $port);
socket_listen($socket);

/* Web socket server created, and listening*/

echo "GPT interface server listening on $host:$port\n";

/* accepting clients */

$clients = array();
while (true) {
        $read = array();
        $read[0] = $socket;
        $i=0;
        foreach($clients as $value) {
                $read[$i+1] = $value;
                $i++;
        }

        $write = $except = null;

        if(socket_select($read, $write, $except, null)==false); else {

        if (in_array($socket, $read)) {
                $clients[] = $new_socket = socket_accept($socket);
                $prompt[(int)$new_socket]="";
                $question[(int)$new_socket]="";
                $header = socket_read($new_socket, 1024);
                perform_handshaking($header, $new_socket, $host, $port);
                socket_getpeername($new_socket, $ip);
                echo "New client connected: $ip\n";

                /* Sending intro to client */

                $state[(int)$new_socket] = 0;
                $intro="You have an empty mind at disposal. Please state who this mind is.";
                $msg = mask($intro);
                send_message($msg,$new_socket);
                $intro="Example : You are a robot that knows everything about IT. You will answer any of my questions.";
                $msg = mask($intro);
                send_message($msg,$new_socket);

                $key = array_search($socket, $read);
                unset($read[$key]);
    }

/* Connections accepted, accepting prompts */

    foreach ($read as $client) {

        $data = @socket_read($client, 1024);

        /* If client sends blank or is disconnected, clear them up from queue */

        if ($data === false || strlen($data)==0) {
            $key = array_search($client, $clients);
            socket_getpeername($client, $ip);
            echo "Client disconnected: $ip\n";
            unset($clients[$key]);
            continue;
        }

        /* If we are at the first state where you set the mind */

        if($state[(int)$client]==0) {
                $data = unmask($data);
                $mind[(int)$client] = $data;

                $intro="Thank you. I am generated. Please chat with me now.";
                $msg = mask($intro);
                send_message($msg,$client);

                $state[(int)$client]=1;

        } else {

        /* If we are in regular mode where user sends prompts */

                $data = unmask($data);
                $question[(int)$client] = $data;

        /* Prompt generation */

                $prompt[(int)$client] .= "\nQ:".$question[(int)$client]."\nA:";
                $response = $openai->completion("text-davinci-003",$mind[(int)$client]."\n".$prompt[(int)$client], 512);
                $answer =(json_decode($response)->choices[0]->text);

        /* generating and sending answer */

                $prompt[(int)$client] .=  $answer;
                $msg = mask($answer);
                send_message($msg,$client);


                }
            }
        }
}
?>
