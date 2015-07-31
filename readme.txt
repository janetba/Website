This service was created using silix and php. 
I use amazon S3 bucket to store the images, and an Amazon Stack
containing a php server to host it. I am using several twig files
to display the data stored (pictures) and to navigate through the page.


Architecture: 

The core of the system can be found in app.php. The app.php file, 
starts by creating an app stored in the variable $app. Then the app
is registered and a connection to the s3 bucket is created. 

After the connection is started a starting page wil load. The page
start.twig will render with two buttons, add picture and get picture. 
Add picture will then render add.twig. 

Inside add.twig a button back to the main page is added as well as a 
button to retreive the images. There is also a form added that allows
a user to browse a for a picture. The text box gets populated with the
name of the item chose. The user can then press the submit button. When
the use hits submit a control that controls the form, looks for the 
file's extension. If the extension is not jpeg it throws and exception,
and false is returned in a jason format. If the file is a jpg, then it is
added to the bucket. The file is added to the bucket given a unique name. 
The name is the name of the files along with the time when it was submited. 
Time being unique will generate a unique name for every file the users submit. 
The name of the file and if it succedded will be returned in a json format. 

To retrieve the file the user needs to click the retreive photo button. 
Then the index.twig will be rendered. Inside that form the user can enter
that name, with the option of entering url parameters for resizing the image. 
If no parameters are enetered then the image will be resized to 200 by 200. 
If the length and with parameters are set in the url then the image will be 
resized to that. To add parameters in the url the user simply needs to add
?nheight=100&nwidth=399. When the user presses sumbmit then a couple of things happen. 

That portion of the service doesn't work, I was bussy this week and couldn't
finish up the paramater passing to the twig file from the controller in app.
It is currently set to 200 so all images requested are going to be dispalyed in 200 by 200.

One if the file is not present in the bucket then the user will be notified. 
If the file is present, the image will be resized appropiately and displayed. 


The image is hosted in http://52.24.123.149/ thrugh amazon aws, it will be stop 
working on Saturday morning, please look at it before then. 
