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

// Setup the database
$app['db.table'] = DB_TABLE;
$app['db.dsn'] = 'mysql:dbname=' . DB_NAME . ';host=' . DB_HOST;
$app['db'] = $app->share(function ($app) {
    return new PDO($app['db.dsn'], DB_USER, DB_PASSWORD);
});

// Handle the index/list page
$app->match('/', function () use ($app) {
	
    return $app['twig']->render('start.twig');
});

// Handle the index/list page
$app->match('/get', function (Request $request) use ($app, &$pictureCounter, &$picturemap) {
	
	if('POST' == $request->getMethod())
	{ 
		$images = null;
		try{
			
		   $file = $request->request->get('photoIndex');
          
            echo "key Retrieved: $file";		  
			
			$query = $app['db']->prepare("SELECT url, caption FROM {$app['db.table']} WHERE url == $file");
			$images = $query->execute() ? $query->fetchAll(PDO::FETCH_ASSOC) : array();
			
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
			
			$GLOBALS['pictureCounter'] = $GLOBALS['pictureCounter'] + 1; 
		
			echo "picturecounte: ";
			echo $GLOBALS['pictureCounter'];
			
			
            // Save the photo record to the database
            $query = $app['db']->prepare("INSERT INTO {$app['db.table']} (url, caption) VALUES (:url, :caption)");
            $data = array(
                ':url'     => "http://{$app['aws.bucket']}.s3.amazonaws.com/{$key}",
                ':caption' => $request->request->get('photoCaption') ?: 'My cool photo!',
            );
            if (!$query->execute($data)) {
                throw new \RuntimeException('Saving the photo to the database failed.');
            }
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