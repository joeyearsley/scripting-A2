<?php session_start();
/* Start a session before doing anything else, check if topic count session is set
	if it is, check that the addTopic Post var has been set, then set the session to the
	topic count if its set.
	Otherwise set it to the default 4 */
if(isset($_SESSION['tC'])){
	if(isset($_POST['addTopic'])){
		$_SESSION['tC'] = $_POST['addTopic'];
	}
}else{
	$_SESSION['tC'] = 4;
}
 ?>
<!-- Start the HTML with doctype, a header with title and then open the body tag -->
<!DOCTYPE html>
<html>
	<head>
		<title>Lab Booking System</title>
	</head>
	<body>
	<?php


			//Set topic count to the session, as the session is definitely set by now.
			$topicCount = $_SESSION['tC'];
		
			//check if the submit button has been pressed, if it has set it variable b.
			if(isset($_POST['submit'])){
				$b = $_POST['submit'];
			}
			//check students been selected. If it has set its variable for use later on
			if(isset($_POST['student'])){
				$student = $_POST['student'];
			}
			//other wise set to -1, so it can't confused user 0 booking already
			else{ $student = -1; }
			//if the lab is set, then set the variable
			if(isset($_POST['lab'])){
				$lab = $_POST['lab'];
			}
		
			// set the connection in the format HOST, USERNAME, PASSWORD, DB SCHEMA
			$link = mysqli_connect('mysql', 'u2jey', 'icebaby','u2jey');
			//Query to see if student has already booked, empty set if they haven't
			$userQuery = "SELECT * FROM Booking WHERE Student_ID ='" . $student ."'";
			//execute and save results set into userResult
			$userResult = mysqli_query($link , $userQuery);
			//see how many labs are full
			$labQuery = "SELECT Lab_Allocated, Capacity FROM Labs WHERE Lab_Allocated = Capacity";
			//execute and save results set into labResult
			$labResult = mysqli_query($link, $labQuery);
		
			//Create some divs to make the page look nicer and center everything, make a nice border
			echo "<div style='margin-left:auto; margin-right:auto; margin-top: 15%; border-style:solid; border-width:1px; width:700px;'>";
			//Create an inner div so the paragraph tags are centered also and to allow for extra styling
			echo "<div style='text-align:center; margin-top: 5%; margin-bottom: 5%;'>";
			/* Use the inbuilt row counter to see if the student has a booking
			   Outputting the error message and their ID */
			if(mysqli_num_rows($userResult) != 0){
				echo "<p>ERROR: You Have Already Booked A Lab";
				echo "<br /> Student ID: " . $student ."</p>";
		
			}
			/* Otherwise check if all the labs are booked, the resulting set would return 3
			  Outputting the correct error message as necessary.
			*/
			else if(mysqli_num_rows($labResult) == 3 ){
				echo "<p>ERROR: All Labs Booked</p>";
			}	
			/* Otherwise see if the all the labs aren't booked and that the submit button 
			   has actually been pressed.
			*/
			else if((mysqli_num_rows($labResult) != 3)&&($b == 'Book')){
				/* update the labs upon lab id where the allocated doesn't equal the capacity.
				   Used in race condition so next item in queue doesn't over fill the lab.
				   Check the allocation as the locks use a queue system.
				*/
				$incrementLab = "UPDATE Labs SET Lab_Allocated = Lab_Allocated+1 WHERE Lab_ID = " . $lab . " AND Lab_Allocated != Capacity"; 
				//execute and save results set into increment
				$increment = mysqli_query( $link, $incrementLab );
			
				/* check the affected rows, if 0 then capacity condition failed, hence pick another lab
				*/ 	
				if(mysqli_affected_rows($link) == 0)
					{
					  echo "<p>ERROR: Lab Full, Please Book Another Lab.</p>";
					  //jump down to the form section to output the information again.
					  goto Form;	
				}else{
					/* Been incremented, hence finish the booking
					   insert the students booking into the lab
					*/
					$bookLab = "INSERT INTO Booking (Student_ID, Lab_ID) VALUES  (" . $student . "," . $lab . ")";	
					//execute the bookLab query.
					$book = mysqli_query( $link, $bookLab );
					/* Go through all the topics in the post array.
					   Use topicCount as finish point	
					*/
					for($i=0; $i<$topicCount; $i++){
						//set variables to relevant post variable, allows easier referencing
						$tup = $_POST['tup'.$i];
						$topic = $_POST['topic'.$i];
						//use inbuilt escape function to allow back slahes, quotes etc..
						$topic = mysqli_real_escape_string($link, $topic);
						//if the topic isn't empty insert it
						if(!empty($topic)){
							$insertTopic = "INSERT INTO Topics (StudentID, GoodBad, TopicName) VALUES ('".$student."','".$tup."','".$topic."')";
							// execute the insertTopic query
							$insertT = mysqli_query($link, $insertTopic);
							//if either fail then make the web page die and output the last error
							if(!$insertT | !$book){
								  die('Could not update data: ' . mysqli_error($link));
							}
						}
					}
					//managed to pass all checks and book succesfully
					echo "<p>Booked Lab Successfully\n<p>";
				}
			}else{
			//for goto to allow for jumping when labs get booked up mid booking
				Form:
					//check if labs are all full, if in the queue for the DB all labs might get booked.
					if(mysqli_num_rows($labResult1) == 3 ){
						echo "<p>ERROR: All Labs Booked</p>";
					}else{
						//otherwise pring the form, calling this page.
						echo "<form action='test.php' method='post'>";
						/* print the students name, and create a dropdown box, filling the 
							options with the students and their ids (used for submission)
						*/
						echo "Student Name:";
						echo " <select name='student'>";
					
						/* Select students where they aren't already in the booking table, hence don't already have a booking
						*/
						$query = "SELECT * FROM Students WHERE NOT EXISTS (SELECT * FROM Booking WHERE Students.Student_ID = Booking.Student_ID)";
						/* Store the selection in result
						*/
						$result = mysqli_query($link, $query);
					
						/* While the array result isn't empty, use the inbuilt function to go 
						   through the array, checking if the students the current user, if 
						   they are then put their name to the top, i.e. select 
						*/
						while ($row = mysqli_fetch_array($result)) {
							if($student == $row['Student_ID']){
								echo "<option value='" . $row['Student_ID'] . "' selected>" . $row['Student_Name'] . "</option>";
							}else{
								echo "<option value='" . $row['Student_ID'] . "'>" . $row['Student_Name'] . "</option>";
							}
						}
	
						echo "</select>";
						//same as above pretty much
						echo "&nbsp;&nbsp;&nbsp;Labs Available:";
						echo " <select name='lab'>";
							/* Select all labs which aren't full, and lock the table for writing
							   Store the Labs which aren't full.	
							*/
							$query2 = "SELECT * FROM Labs WHERE Capacity != Lab_Allocated LOCK IN SHARE MODE" ;
							$result = mysqli_query($link, $query2);
							//Go through the array like above
							while ($row = mysqli_fetch_array($result)) {
								if($lab == $row['Lab_ID']){
									echo "<option value='" . $row['Lab_ID'] . "' selected>" . $row['Lab_Session'] . "</option>";
								}else{
									echo "<option value='" . $row['Lab_ID'] . "'>" . $row['Lab_Session'] . "</option>";
								}
							}
		
						echo "</select>";
						echo "<br />";
						echo "<p>N.B Empty Topics aren't saved </p>";
						/* Create a table, center it as the div doesn't center it.
						   Add some margins to make it neater.
						   Give it 2 Headers for the topic name and a drop down making it easier
						   for lecturers to normalize into topics understood, and topics not
						   understood.
						   Then loop till topicCount to output the amount of topic fields
						   the user wants, default 4.
						   Also set these values if set, otherwise they equal null.	
						*/
						echo "<table style='margin-left: auto; margin-right: auto; margin-top:20px; margin-bottom:20px;' >";
							echo "<tr>";
								echo "<th>Topic Name</th>";
								echo "<th>Have you understood or had problems with this topic?</th>";
							echo "</tr>";
		
							for($i=0; $i<$topicCount; $i++){
								echo "<tr>";
										echo "<td><input type='text' name='topic" . $i ."'  value='" . $_POST['topic'.$i] . "'/></td>";
										echo "<td>";
											echo"<center>";
												echo "<select name='tup" . $i ."'>";
													if($_POST['tup'.$i] == 'p'){
														echo "<option Value='u'>Understood</option>";
														echo "<option Value='p' selected>Had a problem</option>";
													}else{
														echo "<option Value='u'>Understood</option>";
														echo "<option Value='p'>Had a problem</option>";
													}
												echo "</select>";
											echo "</center>";
										echo "</td>";
							
									echo "</tr>";
						
					
							}
						echo "</table>";
						//add a button to increase the topicCount, then repaint the page.
						echo "<button name='addTopic' Value='" . ++$topicCount . "'  type='submit'>Add Topic</button>";
						//add another button to finally submit the page.
						echo "<input type='submit' name='submit' Value='Book' />";
					echo "</form>";
					}
				}
			echo "</div>";
		echo "</div>";
	/* close the connection, this is ok as the DB keeps track of locks and connections, so
		the lock is kept intack.
	*/
	mysqli_close($link);	
	?>
	<!-- Finish up the HTML -->
	</body>
</html>
