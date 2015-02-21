<?php 
/**
 *  Very quick and dirty. 45mins, don't judge me ! 
 * 
 * Database schema 
 * ==========================
 * Table: Story 
 * 	id (int) 		The ID of the story node
 *  parent (int)	The ID of the parent story node, 0 is no parent or root/starting node. 
 *  title (text)	The action that lead to this story node. 
 *  body (text)		The body or test of the story node. 
 *  view (int)		The amount of times this page has been viewed. 
 *  type (text)		The type of node this is. 
 *                     * Decision - A normal node that has choices. 
 *                     * Catastrophic - A bad ending. This node will not allow choices to be added. 
 *                     * Successful - A good ending.  This node will not allow choices to be added. 
 *
 *
 * ToDo: 
 * - Consider using monolog as a logging system https://github.com/Seldaek/monolog
 * - Create a history tree that shows the steps that you have taken. 
 * - Better user input sanitizing use something like the http://htmlpurifier.org/
 */ 


require_once( 'MyDB.class.php') ; 

class CYOA 
{
	private $db ; 

	function __construct() {
	  	// Open the database. 
	  	$this->db = new MyDB(); 

	  	// Check the database 
	  	$this->SystemCheck(); 
  }

  /**
   * Checks to see if the database has been created. 
   * If not, creates it. 
   */
  private function SystemCheck() 
  {
	  	if( is_null( $this->db) ) {
	  		echo "Error: No database connection\n"; 
	  		die(); 
	  	}

	  	// Create the data if it does not exist.. 
	  	$sql = 'CREATE TABLE IF NOT EXISTS story (id INTEGER  PRIMARY KEY ASC, parent INTEGER , user INTEGER , title TEXT, body TEXT, views INTEGER, type TEXT )' ; 
		if( ! $this->db->exec( $sql) ) {
			echo "Error: Could not query the database with this sql statment. \n". $sql ."\n"; 
			die(); 	
		}

  }

  private function Clean( $input ) {

  	// ToDo: 
  	// Just a warning, you should not use this type of regexp to sanitize user input for a website. 
  	// There is just too many ways to get around it. For sanitizing use something like the http://htmlpurifier.org/ library
  	return preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', "", $input );
  }

  private function Markdown( $input ) {
  	// This will be replaced with a MarkDown parser. 
  	return str_replace( "\n", "<br />\n", $input ); 
  }


 	private function InsertStoryNode ( $storyNode ) 
 	{
 		if( ! isset( $storyNode['parent'] ) || 
 			! isset( $storyNode['title']  ) || 
 			! isset( $storyNode['body']   ) || 
 			! isset( $storyNode['user']   ) ) 
 		{
 			echo "Error: Missing required prameters";
 			return FALSE; 
 		}

 		// Default for the type 
 		if( ! isset( $storyNode['type'] ) ) {
 			$storyNode['type'] = 'decision' ; 
 		}

		// Get the story node 
		$sql = 'INSERT INTO story ( parent, title, body, user, type ) VALUES ( :parent,:title,:body,:user,:type ) ;' ; 
		$statement = $this->db->prepare($sql);
		$statement->bindValue(':parent', $this->Clean( $storyNode['parent'] ) );
		$statement->bindValue(':title',  $this->Clean( $storyNode['title']  ) );
		$statement->bindValue(':body',   $this->Clean( $storyNode['body']   ) );
		$statement->bindValue(':user',   $this->Clean( $storyNode['user']   ) );
		$statement->bindValue(':type',   $this->Clean( $storyNode['type']   ) );
		
		if( $statement->execute() == FALSE ) {
			echo "Error: Could not insert the story node\n"; 
			return FALSE ; 
		}

		return $this->db->lastInsertRowID () ; 
 	}



 	public function GetStoryNode( $id ) 
 	{
 		// Update the view count. 
		$sql = 'UPDATE story SET views = views + 1 WHERE id=:id;' ; 
		$statement = $this->db->prepare($sql);
		$statement->bindValue(':id', $id );
		$results = $statement->execute();
		if( $results == FALSE ) {
			return FALSE ; 
		}

		// Get the story node 
		$sql = 'SELECT * FROM story WHERE id=:id;' ; 
		$statement = $this->db->prepare($sql);
		$statement->bindValue(':id', $id );
		$results = $statement->execute();
		if( $results == FALSE ) {
			return FALSE ; 
		}

		// Get the story node assoc 
		$storyNode = $results->fetchArray( SQLITE3_ASSOC ) ; 

		// Get the choices (childeren) for this node. 
		$sql = 'SELECT * FROM story WHERE parent=:id ORDER BY views DESC; '; 
		$statement = $this->db->prepare($sql);
		$statement->bindValue(':id', $id );
		$results = $statement->execute();
		if( $results == FALSE ) {
			// This is an MySQL error. 
			// This error will not happen if there arn't any childeren.
			return FALSE ; 
		}

		// Add the choices to the story node. 
		while( $row = $results->fetchArray( SQLITE3_ASSOC ) ) {
			$storyNode['children'][] = $row ; 
		}
		
		// return the story node  
		return $storyNode ; 
 	}

	public function Parse( $request ) 
	{
		// If the story ID is not set, then default to the root node. 
		if( ! isset( $request['storyid'] ) ) {
			$request['storyid'] = 1 ; 
		}

		// Default action is GET,  
		if( ! isset( $request['action'] ) ) {
			$request['action'] = 'get' ; 
		}


		switch ( strtolower($request['action']) ) {
			case 'get':
				$this->ActionGet( $request ); 
				break;
			
			case 'add':
				$this->ActionAdd( $request ); 
				break;
			
			default:
				echo 'Error: unknown action, action='. $request['action']  ;
				die(); 
				break;
		}

		$this->Debug(); 
	}


	private function ActionAdd( $request ) {
		$results = $this->InsertStoryNode( $request ) ; 
		if( $results == FALSE ) {
			echo "Error: Could not insert new story"; 
			die(); 
		}

		// It looks like the node was added correctly. 
		// Lets display the new node. 
		$this->Parse( array( 'storyid'=>$results ) ); 
		return ; 
	}

	private function ActionGet( $request ) 
	{
		// Get the storyNode by the storyid 
		$storyNode = $this->GetStoryNode( $request['storyid'] ) ; 

		// Only print the undo if there is somewhere to go to. 
		if( $storyNode['id'] != 1 ) {
			echo '<span class="glyphicon glyphicon-backward" aria-hidden="true"></span> <a href="?&storyid='. $storyNode['parent'] .'">Go back (undo) </a><br />'; 
		}

		// Print the story to the screen 
		echo '<h3>'. $storyNode['title'] .'</h3>'; 
		echo '<p>'. $this->Markdown( $storyNode['body'] ) .'</p>' ; 

		if( $storyNode['type'] == 'decision' ) {
			echo '<strong>What do you want to do next?</strong><br />';

			// Print the stories childern to the screen. 
			if( isset( $storyNode['children'] ) ) {
				
				echo '<ul>';
				foreach ( $storyNode['children'] as $childStoryNode ) {
					echo '<li><a href="?storyid='. $childStoryNode['id'] .'">'. $childStoryNode['title'] . '</a> <span class="label label-info">'. $childStoryNode['views'] .'</span></li>'; 
				}
				echo '</ul>';
			} else {
				echo "No more choices, Why don't you add a new choice?";
			}

			// Allow the user to submit a new choice 
	 		echo '<h3>Add a new choice</h3>';
	 		echo '<form action="?">';
	 		echo '<input type="hidden" name="action" value="add" />';
	 		echo '<input type="hidden" name="user" value="1" />';
	 		echo '<input type="hidden" name="parent" value="'. $request['storyid'] .'" />';
	 		echo '<table>';
	 		echo '<tr><th valign="top">Choice</th><td><input type="text" name="title"></td></tr>';
	 		echo '<tr><th valign="top">Body</th><td><textarea style="width: 400px; height: 200px;" name="body"></textarea></td></tr>';
	 		echo '<tr><th valign="top">Type</th><td><select name="type"><option value="decision" >Decision - More choices to come</option><option value="catastrophic" >Catastrophic - Bad ending</option><option value="successful" >Successful - Good ending</option></select></td></tr>';
	 		echo '</table>';
	 		echo '<input type="submit">';
			echo '</form>';
		
		} 
		else 
		{
			// This is the end of a story line. 
			echo '<a href="?storyid='. $storyNode['parent'] .'">Start over?</a></li>'; 
		}
	}


	private function Debug() 
	{
		return ; 
		// Get the story node 
		echo "\nFull story database dump\n";
		$sql = 'SELECT * FROM story ;' ; 
		$statement = $this->db->prepare($sql);
		$results = $statement->execute();
		// Debug print results to screen. 
		echo '<table border="1">';
		$first = true ; 
		while ($row = $results->fetchArray( SQLITE3_ASSOC )) {

			// Print headers if needed. 
			if( $first ) {
				$first = false; 
				echo '<tr>';
				echo '<th>Actions</th>';
				foreach( $row as $key=>$col ) {
					echo '<th>'. $key . '</th>';
				}	
				echo '</tr>';
			}

			// Print data. 
			echo '<tr>';
			foreach( $row as $key=>$col ) {
				// Print the actions for this data. 
				if( $key == 'id' ) {
					echo '<td>';
					echo '<a href="?storyid='. $col.'">Goto</a> | ';
					echo '<a href="?action=delete&storyid='. $col.'" onclick="return confirm(\'Are you sure you want to delete this StoryNode?\')" >Delete</a> ';
					echo '</td>';	
				}
				// Print the actual value 
				echo '<td>'. $col . '</td>';
			}
		  echo '</tr>';
		}
		echo '</table>';
	}
}

?>