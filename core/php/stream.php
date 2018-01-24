<?php 
// Default movie 
$movie = 'movie/movie1.mov'; 
// pretend movie is the param passed to the php script 
if(isset($_GET['movie'])) 
{ 
    // do your query to get the proper movie 
    // for $_GET['movie'] from your database 
    $movie = result_from_my_database_for($_GET['movie']); 
} 

header('Content-type: application/x-rtsp-tunnelled'); 
readfile('rtsp://'.$movie); 
?>
