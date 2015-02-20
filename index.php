<pre>
<?php 

require_once( 'cyoa.class.php' );

$instance = new CYOA() ; 
$instance->Parse( $_REQUEST ) ; 


echo "\n_REQUEST\n"; 
var_dump( $_REQUEST ) ; 

?> 
<pre>