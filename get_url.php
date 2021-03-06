<?php

$base_dir = dirname(__FILE__);

# Load main config file.
require_once $base_dir . "/conf_default.php";
require_once $base_dir . "/tools.php";

# Include user-defined overrides if they exist.
if( file_exists( $base_dir . "/conf.php" ) ) {
  include_once $base_dir . "/conf.php";
}

#header('Content-type: application/json');

$site_id = is_numeric($_REQUEST['site_id']) ?$_REQUEST['site_id'] : -1;

if ( $_REQUEST['site_id'] == -1 ) {

    $record = get_curl_timings_with_headers($_GET['url']);
    
    if ( isset($_REQUEST['json']) && $_REQUEST['json'] == 1 ) {
      header('Content-type: application/json');
      print json_encode($record);
      exit(1);
    }
    
    $results = array();
    $results["-1"] = $record;
    print_url_results($results);    

} else if ( $site_id == -100 ) {

    $mh = curl_multi_init();

    // Get results from all remotes         
    foreach ( $conf['remotes'] as $id => $remote ) {

      $url = $remote['base_url'] . "get_url.php?json=1&site_id=-1&url=" . htmlentities($_REQUEST['url']);
      $url_parts = parse_url($url);
      $curly[$id] = curl_init();    
      curl_setopt($curly[$id], CURLOPT_HEADER, 1);
      curl_setopt($curly[$id], CURLOPT_TIMEOUT, 4);
      curl_setopt($curly[$id], CURLOPT_RETURNTRANSFER, 1);
      switch ( $url_parts['scheme'] ) {
          case "http":
            curl_setopt($curly[$id], CURLOPT_PROTOCOLS, CURLPROTO_HTTP);
            curl_setopt($curly[$id], CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP);
            break;
          case "https":
            curl_setopt($curly[$id], CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
            curl_setopt($curly[$id], CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS);
            break;
          default:
            die("<h3>Invalid protocol supplied. You need either http:// or https://</h3>");
      } 
      
      curl_setopt($curly[$id],CURLOPT_ENCODING , "gzip"); 
      curl_setopt($curly[$id], CURLOPT_URL, $url);
      curl_multi_add_handle($mh, $curly[$id]);    
    }
    // execute the handles
    $running = null;
    do {
      curl_multi_exec($mh, $running);
    } while($running > 0);

    $results = array();
    
    foreach($curly as $id => $c) {
      
      if(curl_errno($c)) {
        print "<h3>" . curl_error($c) . "</h3>";
      }
      
      $response = curl_multi_getcontent($c);
      
      if ( $response != "" ) {
        list($header, $content) = explode("\r\n\r\n", $response);
        $results[$id] = json_decode($content, TRUE);
      }
      
    }
    
    print_url_results($results);
    
} else if ( isset($conf['remotes'][$site_id]['name'] ) ) {
    
    $url = $conf['remotes'][$site_id]['base_url'] . "get_url.php?json=1&site_id=-1&url=" . htmlentities($_REQUEST['url']);

    $results[$site_id] = json_decode( file_get_contents($url) , TRUE );
    print_url_results($results);
    
} else {
    die("No valid site_id supplied");
}
?>
<script>
$(function(){

    $(".http_headers").button();

});
</script>
