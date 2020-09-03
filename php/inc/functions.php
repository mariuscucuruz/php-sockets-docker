<?php

/**
 * @param $address
 * @param $port
 * @param string $type
 * @param bool   $verbose
 *
 * @return bool|false|resource
 *
 * Attempts to create a socket for either a client (default) or a server.
 *
 * Make sure the port is open on the host server:
 * $ sudo ufw allow from any to any port <$port> proto tcp
 */
function createSocket($address, $port, $type = 'client', $verbose = false)
{
    /* Create a TCP/IP socket. */
    if (!($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP))) {
        echo '>> socket_create() failed: '.getSocketError().".\n";

        return false;
    }

    if ($verbose) {
        echo ">> Socket created ok....\n";
    }

    if ($type === 'server') {
        // only a server will need to bind (not the clients)
        if (!($result = socket_bind($sock, $address, $port))) {
            echo '>> socket_bind() failed: '.getSocketError()."\n";

            return false;
        }

        // confirm progress on screen
        if ($verbose) {
            echo ">> Socket bind ok....\n";
        }

        if (!($result = socket_listen($sock, 10))) {
            echo '>> socket_listen() failed: '.getSocketError()."\n";

            return false;
        }

        // confirm progress on screen
        if ($verbose) {
            echo ">> Listening on $address:$port....\n";
        }
    }

    socket_set_option($sock, SOL_SOCKET, SO_BROADCAST, 1);

    return $sock;
}

/**
 * @param $sock
 * @param $address
 * @param $port
 * @param int $attempts
 *
 * @return bool
 *
 * Attempt to connect to a listening server and
 * retry up to $attempts times until connection
 * is established.
 */
function connectToServer($sock, $address, $port, $attempts = 30)
{
    // set nonblock to allow other connections
    socket_set_nonblock($sock);

    $connRes = false;

    while (!$connRes = @socket_connect($sock, $address, $port) && $attempts) {
        $error = socket_last_error();

        if ($error != SOCKET_EINPROGRESS && $error != SOCKET_EALREADY) {
            $result[] = "Socket connect error ($attempts): ".getSocketError();
            break;
        }

        $result[] = "Connecting ($attempts)...";

        usleep(1000);
        print_r($result, true);
        $attempts--;
    }

    // set blocking to prevent other connections
    socket_set_block($sock);

    return $connRes;
}

/**
 * @return string
 *
 * Generic method to get last error reported by `socket`
 * and print it in a friendly format.
 */
function getSocketError()
{
    $errCode = socket_last_error();
    $errMsg = socket_strerror($errCode);

    socket_clear_error();

    return "Reason: $errMsg [$errCode]";
}

/**
 * @param $sockClient
 * @param $message
 *
 * @return false|int
 *
 * Send a message to socket.
 */
function socketWrite($sockClient, $message)
{
    // append new line to signal end of transmission
    $message .= "\n";

    return socket_write($sockClient, $message, 65536);
}

/**
 * @return string
 *
 * Run `dig` to get public IP.
 */
function getPublicIP()
{
    $publicIP = shell_exec("dig @resolver1.opendns.com ANY myip.opendns.com +short");

    return trim($publicIP);
}

/**
 * Connect to VPN in the background.
 */
function vpnConnect()
{
    shell_exec("./scripts/vypr-ovpn-connect.sh --skip_update --skip_download  > /dev/null 2>/dev/null &");
}

/**
 * Run `vyprvpn.sh --disconnect`
 */
function vpnDisconnect()
{
    shell_exec("./scripts/vypr-ovpn-connect.sh --skip_update --skip_download --disconnect");
}

/**
 * Attempt to change IP by dis/re-connecting to VPN
 */
function vpnReconnect()
{
    shell_exec("./scripts/vypr-ovpn-connect.sh --skip_update --skip_download --reconnect > /dev/null 2>/dev/null &");
}

/**
 * @return bool
 *
 * Return TRUE if current IP differs from ORIGINAL_PUBLIC_IP.
 */
function vpnIsConnected()
{
    return (ORIGINAL_PUBLIC_IP !== getPublicIP());
}

/**
 * @return array
 *
 * Get a static list of proxy definitions.
 */
function getListOfProxies()
{
    return [
        [
            'name' => 'first proxy',
            'address' => '127.0.0.1',
            'port' => '9199',
        ],
        [
            'name' => '2nd proxy',
            'address' => '127.0.0.1',
            'port' => '9199',
        ],
    ];
}

/**
 * Send a Message to a Slack Channel.
 *
 * You can either:
 * - create an app (https://api.slack.com/apps) with an `incoming web-hook` for
 * a certain channel (e.g. https://api.slack.com/apps/<your-app-id>/incoming-webhooks);
 * - or create a `custom integration` on your workspace with an `incoming web-hook`
 * on specific channel (e.g. https://<domain>.slack.com/apps/manage/custom-integrations);
 *
 * @param string $message The message to post into a channel.
 * @return boolean
 */
function sendToSlack($message)
{
    $slackWebHookUrl = getenv('SLACK_WEBHOOK');

    $payload = [
        'text' => $message
    ];

    $ch = curl_init($slackWebHookUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $result = curl_exec($ch);
    curl_close($ch);

    return $result;
}
