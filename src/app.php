<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../db-connect.php';
use Aws\S3\Enum\CannedAcl;
use Aws\S3\S3Client;
use Aws\Silex\AwsServiceProvider;
use Silex\Application;
use Silex\Provider\TwigServiceProvider;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;


// Setup the application
$app = new Application();
$app->register(new TwigServiceProvider, array(
    'twig.path' => __DIR__ . '/templates',
));

// Setup the AWS SDK for PHP
$app->register(new AwsServiceProvider());
$app['aws.bucket'] = $app->share(function ($app) {
    // Make sure the bucket exists
    $s3 = $app['aws']->get('s3');
    if (!$s3->doesBucketExist(S3_BUCKET)) {
        die('You need to create the Amazon S3 bucket "' . S3_BUCKET . '" before running this app.');
    }
    return S3_BUCKET;
});

// Handle the index/list page
$app->match('/', function () use ($app) {
	
    return $app['twig']->render('start.twig');
});

// Handle the index/list page
$app->match('/get', function (Request $request ) use ($app) {
	
	if('POST' == $request->getMethod())
	{
		$images = null;
		try{
		    $file = $request->request->get('photoIndex');  
			$images = "http://{$app['aws.bucket']}.s3.amazonaws.com/" . $file;
			
			/* $result = $app['aws']->get('s3')->getObject(array(
							   'Bucket' => $app['aws.bucket'],
							   'Key'    => $images,
								));

			if($result == null){
				throw new \InvalidArgumentException('The key is not stored in the service.');
			}
			 */
            ini_set('display_errors', 1);
            error_reporting(E_ALL); 
			
			// Get new sizes
             list($width, $height) = getimagesize($images);
	        
			$newheight = $request->get('height') == null ? 200 : $request->get('height');
			$newwidth = $request->get('width') == null ? 200 : $request->get('width');
			echo "height: $newheight   width: $newwidth";
			// Load
            $thumb = imagecreatetruecolor($newwidth, $newheight);
			$source = imagecreatefromjpeg($images);

	 		// check which is greater height or width
			if($newheight > $newwidth)
			{// force to width
				imagecopyresized($thumb, $source, 0, 0, 0, 0, $newwidth, $newwidth, $width, $height);
			}
			else 
			{// force to height
				imagecopyresized($thumb, $source, 0, 0, 0, 0, $newheight, $newheight, $width, $height);
			}
			header("Content-Type:image/jpeg");
			imagejpeg($thumb);

		}
		catch (Exception $e) {
            // Display an error message
            echo "there was an exception $e";
        }
	}

	return $app['twig']->render('index.twig', array());  

});

// Handle the add/upload page
$app->match('/add', function (Request $request) use ($app) {
    $alert = null;
	$key = null;
    // If the form was submitted, process the input
    if ('POST' == $request->getMethod()) {
        try {
			
            // Make sure the photo was uploaded without error
            $file = $request->files->get('photoFile');
            if (!$file instanceof UploadedFile || $file->getError() || pathinfo($file->getClientOriginalName(),PATHINFO_EXTENSION) !== 'jpg') {
                throw new \InvalidArgumentException('The uploaded photo file is not valid.');
            }
		
			  // Upload the photo to S3
            $key = time() . '-' . strtolower(str_replace(array(' ', '_', '/'), '-', $file->getClientOriginalName()));
            $app['aws']->get('s3')->putObject(array(
                'Bucket' => $app['aws.bucket'],
                'Key'    => $key,
                'Body'   => fopen($file->getPathname(), 'r'),
                'ACL'    => CannedAcl::PUBLIC_READ,
            ));
			
			
            // Display a success message
            $result = true;
        } catch (Exception $e) {
            // Display an error message
            $result = false;
        }
    }
    return $app['twig']->render('add.twig', array(
		'returnval' => json_encode(array('success' => $result, 'imageId' => $key))
    ));
});
$app->run();