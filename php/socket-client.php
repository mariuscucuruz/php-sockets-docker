<?php

include 'inc/common.php';
require_once 'inc/functions.php';

// create a TCP/IP socket
$address = '127.0.0.1';
$port = getenv('SOCKET_PORT');
$sock = createSocket($address, $port, 'client', true);

//Connect socket to remote server
connectToServer($sock, $address, $port) or exit(">>> Could not connect to $address:$port: " . getSocketError() . "\n");

while (true) {
    $serverResponse = socket_read($sock, 1024) or exit('>>> Connection to server failed. ' .getSocketError() . ".\n");
    echo ">>> Connection established: $serverResponse\n";

    sleep(2);#looks pretty

    // initiate
    $message = "ping\n";
    socketWrite($sock, $message) or exit('Could not send data: ' . getSocketError() . ".\n");

    echo ">>> Now we're talking....\n";

    while (true) {
        // read from incoming connection
        if (!$buffer = @socket_read($sock, 1024, PHP_NORMAL_READ)) {
            echo ">>> We've been disconnected: ".getSocketError()."\n";
            break;
        }

        // ignore empty responses
        if ('' === ($buffer = trim($buffer))) {
            continue;
        }

        echo ">>> Server said: $buffer.\n";

        if (in_array($buffer, ['ping', 'pong'])) {
            $command = ($buffer === 'ping') ? 'pong' : 'ping';
            socketWrite($sock, $command);

            sleep(2);#nicer on the eye like this

            echo '>>> We say: '.trim($command).".\n";

            continue;
        }
    }

    socketWrite($sock, ">> About to exit...\n$dashed_separator\n") or exit('>> Could not write data:'.getSocketError()."\n");
}

// free resource
socket_close($sock);
