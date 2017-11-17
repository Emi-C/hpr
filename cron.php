#!/usr/bin/php
<?php

require "twitter/twitteroauth.php";
require "facebook.php";
$data = file_get_contents("php://input");
$objData = json_decode($data);
//Chiavi Twitter
$consumerkey = "IEji4vE4H1hVjDvmkGzWNxjwQ";
$consumersecret = "f8Beu1A9FLMwaNEAOVziCIARW0P0yuCQFDRkLfu4HcQqKKlqp8";
$accesstoken = "2350609783-0WdBNaOwPEJuGAg9ROduCeCx8KrC4n5tAtupIzy";
$accesssecret = "2NmWuX3k8LVPFXhzVP9dlsudOaOImuOhpuCj2xgmXgFgu";
//Chiavi Facebook
$config = array(
    'appId' => '1625350391033819',
    'secret' => '5b7f9f3008532cc759b2115088dd4623'
);
//Chiavi Soundcloud
$consumerId = "f767ae26fde1e1ea4ff33b21b8976206";
$consumerSecret = "c9aa82b72e677526f10df9ffb17cecc1";

//Chiavi Instagram
$idInstagram = "e38ccc6eb2164492847c24f6a79262ef";

//Chiavi Youtube
$idYoutube = "AIzaSyCz6vihgFQTilFuuDEgqEnOpPrMrVAob2U";
//Crea connessione Twitter
function getConnectionWithAccessToken($cons_key, $cons_secret, $oauth_token, $oauth_token_secret)
{
    $connection = new TwitterOAuth($cons_key, $cons_secret, $oauth_token, $oauth_token_secret);
    return $connection;
}

$twitter = getConnectionWithAccessToken($consumerkey, $consumersecret, $accesstoken, $accesssecret);


//Crea connessione Facebook
$facebook = new Facebook($config);
$context = array(
//    'http' => array(
//        'proxy' => $proxy,
//        'request_fulluri' => True,
//    ),$response
);
$context = stream_context_create($context);

$message = array();
$facebookArray = array();
$twitterArray = array();
$instagramArray = array();
$youtubeArray = array();
set_time_limit(0);
$date = new DateTime();
$esci=false;
try {
    $pageFeed = $facebook->api("giroditalia" . '/posts?limit=200');
    $tweets = $twitter->get("https://api.twitter.com/1.1/statuses/user_timeline.json?screen_name=giroditalia&count=300");
    $urlInstagram = 'https://api.instagram.com/v1/users/1318499/media/recent/?&client_id=' . $idInstagram . '&count=200';
    $data = $pageFeed['data'];

    //Facebook
    foreach ($data as $fb) {
        if (!is_null($fb['message'])) {
            $feed = array(
                "chiave" => 'giroditalia',
                "text" => $fb['message'],
                "type" => "facebook",
                "ora" => strtotime($fb['created_time']) + 7200,
                "data" => $fb['created_time'],
                "object_id" => $fb['object_id'],
                'artist_id' => $fb['from']['id'],
                'tipomedia' => $fb['type'],
                "user" => $fb['from']['name'],
                "link" => $fb["link"],
            );
            $message[] = $feed;
            $facebookArray[] = $feed;
        }
    }
    $paginaSuccessiva = $pageFeed['paging']['next'];
    for(; ;){
        $urlFb= explode( "/", $paginaSuccessiva);
        $pageFeed = $facebook->api('giroditalia' .'/'. $urlFb[5]);
        $data = $pageFeed['data'];
        $paginaSuccessiva = $pageFeed['paging']['next'];
        //Facebook
        foreach ($data as $fb) {
            if (!is_null($fb['message'])) {
                $feed = array(
                    "chiave" => 'giroditalia',
                    "text" => $fb['message'],
                    "type" => "facebook",
                    "ora" => strtotime($fb['created_time']) + 7200,
                    "data" => $fb['created_time'],
                    "object_id" => $fb['object_id'],
                    'artist_id' => $fb['from']['id'],
                    'tipomedia' => $fb['type'],
                    "user" => $fb['from']['name'],
                    "link" => $fb["link"],
                );
                $message[] = $feed;
                $facebookArray[] = $feed;
            }
            if (strtotime($fb['created_time']) + 7200 < $date->getTimestamp() - 2592000) {
                $esci = true;
                break;
            }
        }
        if ($esci)
            break;
    }

    $esci=false;

    //Twitter
    foreach ($tweets as $tweet) {
        if (!substr_startswith($tweet->text, "RT")) {
            if (!substr_startswith($tweet->text, "@")) {
                $feed = array(
                    "chiave" => 'giroditalia',
                    "text" => $tweet->text,
                    "type" => "twitter",
                    "ora" => strtotime($tweet->created_at) + 7200,
                    "data" => $tweet->created_at,
                    "media" => $tweet->entities->media[0]->media_url,
                    "user" => $tweet->user->name,
                    "link" => 'https://twitter.com/giroditalia/status/' . $tweet->id,
                );
                $message[] = $feed;
                $twitterArray[] = $feed;
                $postMinimo =($tweet->id)-1;
            }
        }
    }

    for(;;){
        $tweets = $twitter->get("https://api.twitter.com/1.1/statuses/user_timeline.json?screen_name=giroditalia&count=90&max_id=".$postMinimo);
        foreach ($tweets as $tweet) {
            if (!substr_startswith($tweet->text, "RT")) {
                if (!substr_startswith($tweet->text, "@")) {
                    $feed = array(
                        "chiave" => 'giroditalia',
                        "text" => $tweet->text,
                        "type" => "twitter",
                        "ora" => strtotime($tweet->created_at) + 7200,
                        "data" => $tweet->created_at,
                        "media" => $tweet->entities->media[0]->media_url,
                        "user" => $tweet->user->name,
                        "link" => 'https://twitter.com/giroditalia/status/' . $tweet->id,
                    );
                    $message[] = $feed;
                    $twitterArray[] = $feed;
                    $postMinimo =($tweet->id)-1;
                }
            }

            if (strtotime($tweet->created_at) + 7200 < $date->getTimestamp() - 2592000) {
                $esci = true;
                break;
            }
        }
        if ($esci)
            break;
    }
    //Instagram
    $result = file_get_contents($urlInstagram, False, $context);
    $resultDecoded = json_decode($result, true);

    foreach (($resultDecoded['data']) as $insta) {
        $feed = array(
            "chiave" => 'giroditalia',
            "type" => "instagram",
            "text" => $insta['caption']['text'],
            "video" => $insta['videos']['standard_resolution']['url'],
            "user" => $insta['caption']['from']['username'],
            "ora" => $insta['created_time'] + 7200,
            "img_profile" => $insta['caption']['from']['profile_picture'],
            "media" => $insta['images']['standard_resolution']['url'],
            "link" => $insta['link'],
        );
        $message[] = $feed;
        $instagramArray[] = $feed;
        $next_page= $resultDecoded['pagination']['next_url'];
    }

    for(;;){
        $result = file_get_contents($next_page, False, $context);
        $resultDecoded = json_decode($result, true);

        foreach (($resultDecoded['data']) as $insta) {
            $feed = array(
                "chiave" => 'giroditalia',
                "type" => "instagram",
                "text" => $insta['caption']['text'],
                "video" => $insta['videos']['standard_resolution']['url'],
                "user" => $insta['caption']['from']['username'],
                "ora" => $insta['created_time'] + 7200,
                "img_profile" => $insta['caption']['from']['profile_picture'],
                "media" => $insta['images']['standard_resolution']['url'],
                "link" => $insta['link'],
            );
            $message[] = $feed;
            $instagramArray[] = $feed;
            $next_page= $resultDecoded['pagination']['next_url'];
            if ($insta['created_time'] + 7200 < $date->getTimestamp() - 2592000) {
                $esci = true;
                break;
            }
        }
        if ($esci)
            break;
    }

    $username = 'giroditaliaweb';
    $xml = simplexml_load_file(sprintf('http://gdata.youtube.com/feeds/base/users/%s/uploads?alt=rss&v=2&orderby=published&max-results=50', $username));
//Questo qua sotto è per prendere solo quelli di oggi (rischioso, non pubblicano ongi giorno)
//$xml = simplexml_load_file(sprintf('http://gdata.youtube.com/feeds/base/users/%s/uploads/most_popular?alt=rss&v=2&time=today', $username));
    foreach ($xml->channel->item as $item) {
        parse_str(parse_url($item->link, PHP_URL_QUERY), $url_query);
        $id = $url_query['v'];
        $feed = array(
            'video' => 'https://www.youtube.com/embed/' . $id,
            'type' => 'youtube',
            'chiave' => 'giroditaliaweb',
            'user' => 'giroditaliaweb',
            'ora' => strtotime($item->pubDate) + 7200,
            'title' => (string)($item->title),
            'link' => 'http://www.youtube.com/watch?v=' . $id,
        );
        $message[] = $feed;
        $youtubeArray [] = $feed;
    }

} catch (Exception $e) {

}

function sortFunction($a, $b)
{
    return ($b["ora"]) - ($a["ora"]);
}

function substr_startswith($haystack, $needle)
{
    return substr($haystack, 0, strlen($needle)) === $needle;
}



usort($message, "sortFunction");
$fp = fopen('results.json', 'w+r');
fwrite($fp, json_encode($message));
fclose($fp);

/*echo json_encode($totale);*/
?>
