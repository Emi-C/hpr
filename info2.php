<?php

require "twitter/twitteroauth.php";
require_once 'Facebook/autoload.php';

$fb = new Facebook\Facebook([
  'app_id' => '152138878880348',
  'app_secret' => 'f5f444570749cfad19b1474c5d981ec1',
  'default_graph_version' => 'v2.11',
  'default_access_token' => '152138878880348|f5f444570749cfad19b1474c5d981ec1',
  // . . .
  ]);


$message = array();


$fbUsers = array('vascorossi','Ligabue');


foreach ($fbUsers as &$user) {
 

$fbPost= $fb->get('/'.$user.'/posts?limit=1&fields=message,link,created_time,type,name,id,source');


    $fbPost = $fbPost->getGraphEdge();

    		$response_array = $fbPost->asArray();


foreach ($response_array as &$post) {
        if (!is_null($post['message'])) {

        	$date = ((array) $post['created_time']);


            $feed = array(
                "chiave" => $user,
                "text" => $post['message'],
                "type" => "facebook",
                "ora" => strtotime($post['created_time']) + 7200,
                "data" => $date['date'],
                "object_id" => $post['object_id'],
                'artist_id' => $post['from']['id'],
                'tipomedia' => $post['type'],
                "user" => $post['from']['name'],
                "link" => $post["link"],
            );
            $message[] = $feed;
        }

}
    //Facebook

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



?>