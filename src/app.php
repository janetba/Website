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

static $picturemap = array();

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
$app->match('/get/{height}/{width}', function (Request $request) use ($app) {
	
	if('POST' == $request->getMethod())
	{ 
		$images = null;
		try{
			  
		    echo "height $height width $width";
		    $file = $request->request->get('photoIndex');
		    $images = "http://{$app['aws.bucket']}.s3.amazonaws.com/" . $file;
		    $thumb = new Imagick($images);
		   
			//check which is greater height or width
			if($height > $width)
			{//force to width
				$thumb->resizeImage($width,$width,Imagick::FILTER_UNDEFINED,1);
			}
			else 
			{//force to height
				$thumb->resizeImage($height,$height,Imagick::FILTER_UNDEFINED,1);
			}
			echo '<img src="data:image/jpg;base64,'.base64_encode($thumb->getImageBlob()).'" alt="" />';`
			
			return $app['twig']->render('display.twig', array(
			'title'  => 'My Photos',
			'images' => $images,
            ));
		}
		catch (Exception $e) {
            // Display an error message
            echo "there was an exception $e";
        }
	}
	else
	{
		return $app['twig']->render('index.twig', array(
			'title'  => 'My Photos',
			'images' => $images,
		));
	}
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