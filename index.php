<?php

session_start();

require 'vendor/autoload.php';

use Aws\Sts\StsClient;
use Aws\S3\S3Client;
use Aws\Common\Credentials;


//directory name in the s3 bucket that can be unique for any customer
$s3dir = $customer_id = "user_1";

//S3 & accesso S3:
$Bucket = '<Bucket Name>';
$RoleArn = '<Role ARN>';

$auth = array(
    'key'		=> '<AccessKey>', // AccessKey
    'secret'	=> '<SecretKey>' //SecretKey

);

// Client STS is required to create temporary credentials for the user(customer)
$sts = StsClient::factory($auth);


//Let's define the personalized policy for the user(Customer that use the service):
$Policy = '{
					"Version": "2012-10-17",
					"Statement": [
						{
							"Sid": "AllowAllS3ActionsInUserFolder",
							"Effect": "Allow",
							"Action": [
								"s3:GetObject"
							],
							"Resource": [
								"arn:aws:s3:::'.$Bucket.'/'.$s3dir.'/*"
							]
						}
					]
				}';		
$result = $sts->assumeRole(array(
    // RoleArn is required
    'RoleArn' => $RoleArn,
    // RoleSessionName is required
    'RoleSessionName' => session_id(), //customer session ID so the generated credentials are valid only for the session
	'Policy' => $Policy,
    'DurationSeconds' => 3600, //Time in seconds that temporary credentials are valid
    //'ExternalId' => '',
    //'SerialNumber' => 'string',
    //'TokenCode' => 'string',
));

$credentials = $sts->createCredentials($result); // Generate temporary credentials


$s3 = S3Client::factory(array('credentials' => $credentials));

//Function that create the temporary URL signed with the authorization for requested object:
function S3downloadUrl($s3, $Key, $Bucket){
	
	$SignatureParams = array(
		'Bucket'=>$Bucket,
		'signatureVersion' => 'v4',
		'Key'=>$Key,
		'ResponseContentType'=>'application/octet-stream',
		'ResponseContentDisposition'=>'attachment',
		
	);
	return $s3->getObjectUrl($Bucket, $Key, '+1 minute', $SignatureParams);
	
}



/* This part here is only for ilustration only */
?>
<html>
<head>
</head>
<body>
<?php
echo "<p>Your S3 directory is: <b>".$s3dir."</b></p>";

echo "<h2>S3 object list:</h2>";
		
$iterator = $s3->getIterator('ListObjects', array(
	'Bucket' => $Bucket
));

foreach ($iterator as $object) {
	if(strpos($object['Key'],$s3dir."/") === 0){
		echo "<br/><a href='".S3downloadUrl($s3, $object['Key'], $Bucket)."'>".$object['Key']."</a>\n";
	}else{
		echo "<br/><a href='".S3downloadUrl($s3, $object['Key'], $Bucket)."'>".$object['Key']."</a> <small style='color:red;'>The customer doesn't have access to this file</small>\n";
	}
}

?>

</body>
</html>