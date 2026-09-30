<?php

$server = stream_socket_server('tcp://127.0.0.1:0');

if ($server === false) {
    exit(1);
}

echo stream_socket_get_name($server, false).PHP_EOL;
fflush(STDOUT);
$connection = stream_socket_accept($server, 10);

if ($connection === false) {
    exit(1);
}

sleep(10);
fclose($connection);
fclose($server);
