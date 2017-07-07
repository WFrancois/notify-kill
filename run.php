<?php
use Symfony\Component\Yaml\Yaml;

require __DIR__ . '/vendor/autoload.php';

$config = Yaml::parse(file_get_contents(__DIR__ . '/config.yaml'));

$client = new \BlizzardApi\BlizzardClient($config['blizzard']['key'], $config['blizzard']['secret'], 'eu', 'en_gb');
$wow = new \BlizzardApi\Service\WorldOfWarcraft($client);

$guildToCheck = [
    ['method', 'tarren-mill'],
    ['Экзорсус', 'ревущий-фьорд'],
];

$bossToTrack = [
    11780, # Fallen Avatar
    11781, # Kil'jaeden
];

$file = file_get_contents('notified.json');

$alreadyFound = [];

if(!empty($file)) {
    $alreadyFound = json_decode($file, true);
}

foreach($guildToCheck as $guild) {
    $response = $wow->getGuild($guild[1], $guild[0], ['fields' => 'news']);

    $data = json_decode($response->getBody());

    foreach($data->news as $news) {
        if($news->type === 'playerAchievement') {
            if(in_array($news->achievement->id, $bossToTrack) && !in_array($news->timestamp, $alreadyFound)) {
                $message = $guild[0] . ' KILLED ' . $news->achievement->title;
                $alreadyFound[] = $news->timestamp;
                sendSms($config['sms']['key'], $message);
            }
        }
    }
}

file_put_contents('notified.json', json_encode($alreadyFound));

function sendSms($key, $message)
{
    $ch = curl_init();

    $postFields = [
        'token=' . curl_escape($ch, $key),
        'message=' . curl_escape($ch, $message),
    ];

    curl_setopt($ch, CURLOPT_URL, 'https://sms.wisak.eu');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, implode('&', $postFields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    curl_exec($ch);
    curl_close($ch);
}