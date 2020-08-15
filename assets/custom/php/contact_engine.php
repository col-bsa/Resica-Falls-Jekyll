<?php

require '../../vendor/php/autoload.php';
use Mailgun\Mailgun;
use Medoo\Medoo;

require 'secrets.php';

$mailgun = array();
$emailnames = array();

/* * * * * * * * * * * * * * * * * * *
 *          ADMIN VARIABLES          *
 * * * * * * * * * * * * * * * * * * */

$mailgun['domain'] = "resicafalls.org";
$mailgun['from'] = "ResicaFalls.org Contact Form <contact_form@" . $mailgun['domain'] . ">";
$mailgun['subject'] = "ResicaFalls.org Message From: "; // Is added to later in script

$emailnames['steve.mach@resicafalls.org'] = "Steve Mach";
$emailnames['jerry.reed@resicafalls.org'] = "Jerry Reed";
$emailnames['nicholas.mckee@scouting.org'] = "Nick McKee";
$emailnames['chris.brenner@resicafalls.org'] = "Chris Brenner";
$emailnames['john.bickel@scouting.org'] = "John Bickel";
$emailnames['ted.mcdonald@resicafalls.org'] = "Ted McDonald";
$emailnames['camping@scouting.org'] = "Camping Office";
$emailnames['hello@resicafalls.org'] = "ResicaFalls.org Customer Support";

/* * * * * * * * * * * * * * * * * * *
 *    COLLECT HTML FORM POST DATA    *
 * * * * * * * * * * * * * * * * * * */

$user_data = array();
$return_data = array();

$user_data['name'] = trim($_POST['name']);
$user_data['email'] = trim($_POST['email']);
$user_data['recipient'] = trim($_POST['recipient']);
$user_data['message'] = trim($_POST['message']);
$user_data['recaptcha'] = $_POST['g-recaptcha-response'];

$user_data['address'] = $_SERVER['REMOTE_ADDR'];

/* * * * * * * * * * * * * * * * * * *
 *           VALIDATE DATA           *
 * * * * * * * * * * * * * * * * * * */

$hostname = gethostname();
$hostname = stripos($hostname, "prod") >= 0 ? $mailgun['domain'] : $hostname . "." . $mailgun['domain'];

if (empty($user_data['recaptcha']))
  $error_text = "reCAPTCHA was not received.";

if(!isset($error_text))
{
  $recaptcha = new \ReCaptcha\ReCaptcha($SECRET_recaptcha);
  $resp = $recaptcha->setExpectedHostname($hostname)
                    ->verify($user_data['recaptcha'], $user_data['address']);
  if ($resp->isSuccess()) {
    // Verified!
  } else {
    $error_text = "reCAPTCHA was not verified. " . var_dump($resp->getErrorCodes());
  }
}

$inputs = ['name', 'email', 'recipient', 'message'];

foreach ($inputs as $input)
{
  if(!isset($error_text))
  {
    if (empty($user_data[$input]))
      $error_text = ucfirst($input) . " was not received.";
  }
}

if(!isset($error_text))
{
  /* * * * * * * * * * * * * * * * * * *
   *          EMAIL FORM DATA          *
   * * * * * * * * * * * * * * * * * * */

  $mailgun['to_field'] = "";
  if ($emailnames[$user_data['recipient']])
  {
    $mailgun['to_field'] = $emailnames[$user_data['recipient']];
  }
  $mailgun['to_field'] .= "<" . $user_data['recipient'] . ">, ResicaFalls.org Contact Form<contact_form@resicafalls.org>";

  $mailgun['subject'] .= $user_data['name'];

  $send_text = "The following was submitted to ResicaFalls.org/contact." .
    PHP_EOL . PHP_EOL . $user_data['message'] . PHP_EOL . PHP_EOL . $user_data['name'] . PHP_EOL . $user_data['email'];

  $mg = Mailgun::create($SECRET_mailgun);

  $mg->messages()->send($mailgun['domain'], [
    'from'        => $mailgun['from'],
    'to'          => $mailgun['to_field'],
    'h:Reply-To'  => $user_data['name'] . " <" . $user_data['email'] . ">, ResicaFalls.org Contact Form<contact_form@resicafalls.org>",
    'subject'     => $mailgun['subject'],
    'text'        => $send_text
  ]);

  /* * * * * * * * * * * * * * * * * * *
   *          DATABASE INSERT          *
   * * * * * * * * * * * * * * * * * * */

  $database = new Medoo([
    'database_type' => 'sqlite',
    'database_file' => '../../../../database.sqlite'
  ]);

  $database->insert('arc_contactform', [
    'name' => $user_data['name'],
    'email' => $user_data['email'],
    'recipient' => $user_data['recipient'],
    'message' => $user_data['message'],
    'orig_ip' => $user_data['address'],
  ]);
}

/* * * * * * * * * * * * * * * * * * *
 *           RETURN STATUS           *
 * * * * * * * * * * * * * * * * * * */

if (empty($error_text))
  $return_data['success'] = true;
else
{
  $return_data['success'] = false;
  $return_data['error'] = $error_text;
}

echo json_encode($return_data);

?>
