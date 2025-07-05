 <?php
  session_start();
   // Start the session
    $userId = $_SESSION['user_id'];
     header('Content-Type: text/html; charset=utf-8');
      // Database connection setup
      $servername = "localhost"; $username = "root"; $password = "test"; $dbname = "pod_rota";
        $conn = new mysqli($servername, $username, $password, $dbname);
         // Check for database connection error
         if ($conn->connect_error) {
          die("Connection failed: " . $conn->connect_error);
           } // Step 1: Select pod data from timetable where club is 'dolphinsc'
           $query1 = "SELECT pod FROM timetable WHERE club = 'dolphinsc'";
            $result1 = $conn->query($query1);
             // Check for query execution errors
             if (!$result1) {
              die("Error executing query: " . $conn->error);
               } // Create an array to hold swimmer names from timetable
               $pods = [];
                while ($row = $result1->fetch_assoc()) {
                $pods[] = $row['pod'];
                }
                print_r($pods);
                // Step 2: Select swimmer data from swimmerData where club is 'dolphinsc'
                $query2 = "SELECT swimmerData FROM pods WHERE club = 'dolphinsc'";
                 $result2 = $conn->query($query2);
                 // Check for query execution errors
                 if (!$result2) {
                 die("Error executing query: " . $conn->error);
                  } // Create an array to hold the final matching swimmers and their groups
                  $podsList = [];
                   // Step 3: Loop through swimmer data and check for matching swimmer names
                   while ($row = $result2->fetch_assoc()) {
                    if (in_array($row['swimmerData'], $pods)) {
                    // If a match is found, add it to the podsList
                    $podsList[] = ['group' => $row['group'], 'swimmerData' => $row['swimmerData']];
                     }
                     }
                     // Return the final podsList as JSON for better readability
                     echo json_encode($podsList, JSON_PRETTY_PRINT);
                      // Close the database connection
                      $conn->close();
                        print_r($podsList);
                        ?>
