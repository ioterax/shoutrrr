<?php

$server = stream_socket_server('tcp://127.0.0.1:0');

if ($server === false) {
    exit(1);
}

echo stream_socket_get_name($server, false).PHP_EOL;
fflush(STDOUT);
$reads = 0;
$recover = ($argv[1] ?? 'stall') === 'recover';

while ($connection = stream_socket_accept($server, 10)) {
    stream_set_timeout($connection, 10);

    while (($header = fgets($connection)) !== false) {
        $arguments = [];

        for ($index = 0; $index < (int) substr($header, 1); $index++) {
            $length = (int) substr(fgets($connection), 1);
            $arguments[] = stream_get_contents($connection, $length);
            stream_get_contents($connection, 2);
        }

        $command = strtoupper($arguments[0] ?? '');

        if ($command === 'SELECT') {
            fwrite($connection, "+OK\r\n");
        } elseif ($command === 'PING') {
            fwrite($connection, "+PONG\r\n");
        } elseif ($command === 'GET') {
            $reads++;
            echo "GET\n";
            fflush(STDOUT);

            if ($recover && $reads > 1) {
                fwrite($connection, "$9\r\nrecovered\r\n");
            }
        } else {
            exit(1);
        }
    }

    fclose($connection);
}
