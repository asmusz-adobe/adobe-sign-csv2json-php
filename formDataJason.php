<?php 

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include './functions_1.php';

//  Get data from POST Params
$data = json_decode(file_get_contents('php://input'), true);

//  Write data from POST to variables
$agreement_id = $data['agreement_id'];
$token = $data['token'];
$email = $data['sender_email'];
$shard = $data['shard'];

//  Start cUrl process -  Code from POSTMAN "php cUrl"
$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => "https://api." . $shard . ".echosign.com/api/rest/v6/agreements/" . $agreement_id . "/formData",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "GET",
  CURLOPT_HTTPHEADER => array(
    "Accept: */*",
    "Authorization: Bearer " . $token,
    "Cache-Control: no-cache",
    "Connection: keep-alive",
    "Host: api.na2.echosign.com",
    "User-Agent: PostmanRuntime/7.13.0",
    "accept-encoding: gzip, deflate",
    "cache-control: no-cache",
    "x-api-user: email:". $email 
  ),
));

// Run call against Adobe Sign REST to get form data as CSV 
$response = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);

//  Process cUrl data into JSON
if   // Check for cUrl error
    (
        $err
    ) 
{
  echo "cURL Error #:" . $err;
} 

elseif  // Check to see if non-cUrl error but still error from Adobe Sign 
    ( 
        strpos($response, 'agreementId') === false or
        strpos($response, '"code":')
    ) 
{

        // If response does not contain the agreement ID it likely means an
        // error has occurred (like bad permissions etc.) so need to return
        // error as response to call
        header('Content-type: application/json');
        echo $response;
} 
    else   // If valid response -- process data and return JSON 
{ 
    
        //  Create filename for CSV
        $fileName =  $agreement_id . "_data.csv";
        
        //  Create file for csv and write to it using CURL response
        $fp = fopen($fileName, 'w+');
        fwrite($fp, $response);
        fclose($fp);
        
        // Set your CSV feed to newly created file
        $feed = $fileName;
        
        // Arrays we'll use later
        $keys = array();
        $newArray = array();
        // Create object from CSV file
        $data = csvToArray($feed, ',');
        
        // Set number of elements (minus 1 because we shift off the first row)
        $count = count($data) - 1;
        echo $count;
        
        //Use first row for names  
        $labels = array_shift($data);  
        foreach ($labels as $label) {
          $keys[] = $label;
        }
        
        // Add Ids, just in case we want them later
        $keys[] = 'id';
        for ($i = 0; $i < $count; $i++) {
          $data[$i][] = $i;
        }
          
        // Bring it all together
        for ($j = 0; $j < $count; $j++) {
          $d = array_combine($keys, $data[$j]);
          $newArray[$j] = $d;
        }
        // Print it out as JSON
        header('Content-type: application/json');
        echo json_encode($newArray);
        // Next section to write .json file to directory for logging
        $new_json = prettyPrint( json_encode($newArray));
        $json_filename = $agreement_id . "_json_data.json";
        $fjp = fopen($json_filename, 'w+');
        fwrite($fjp,$new_json );
        fclose($fjp);
        
        // ##  Cleanup section --- Use this after testing to remove persistent files once you know process is working as needed
        
        // Uncomment next section to remove JSON file automatically
        unlink($json_filename) or die("Could not delete JSON File.");
        
        // Uncomment next section to remove automatically created .csv file
        unlink($fileName) or die("Couldn't delete csv file.");
       }

?>
