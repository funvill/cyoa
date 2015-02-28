<?php 
	header('Content-Type: application/xml; charset=utf-8');

	require_once( 'cyoa.class.php' );
	$instance = new CYOA() ; 

	// IF the story ID is not set, then default to the front page. 
	if( ! isset( $_REQUEST['storyid'] ) ) {
		$_REQUEST['storyid'] = 1 ; 
	}
	if( isset( $_REQUEST['Digits'] ) ) {
		$_REQUEST['storyid'] = $_REQUEST['Digits'] ; 
	}
	

	// Get the story by the node. 
	$storyNode = $instance->GetStoryNode( $_REQUEST['storyid'] ) ; 

	// Print the XML header 
	echo "<?xml version='1.0' encoding='UTF-8'?>";
?>
<Response>
    <Say><?php echo $storyNode['body'] ; ?></Say>
    <?php 

    if( isset( $storyNode['children'] ) ) 
    { 
		?>
		<Gather timeout="10" finishOnKey="*" action="/voice.php" method="GET" numDigits="1">
		<?php 
			$buttonID = 1 ; 
			foreach ( $storyNode['children'] as $childStoryNode ) {				
	        	echo '<Say>Press '. $childStoryNode['id'] .' then * to select, '. $childStoryNode['title'] .'</Say>'; 
	        }	
		?>
	    </Gather>
	    <?php 
	}
    ?>
</Response>