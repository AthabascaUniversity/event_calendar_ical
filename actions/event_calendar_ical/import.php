<?php

elgg_load_library('elgg:event_calendar');
elgg_load_library('event_calendar_ical:creator');

$container_guid = (int) get_input('container_guid', 0);

// check if upload failed
if (!empty($_FILES['ical_file']['name']) && $_FILES['ical_file']['error'] != 0) {
	register_error(elgg_echo('event_calendar_ical:file:cannotload'));
	forward(REFERER);
}

// must have a file if a new file upload
if (empty($_FILES['ical_file']['name'])) {
  register_error(elgg_echo('event_calendar_ical:file:nofile'));
  forward(REFERER);
}

$thumb = new ElggFile();
$thumb->setMimeType($_FILES['ical_file']['type']);
$thumb->setFilename($_FILES['ical_file']['name']);

// copy the file
move_uploaded_file($_FILES['ical_file']['tmp_name'], $thumb->getFilenameOnFilestore());

$path = pathinfo($thumb->getFilenameOnFilestore());

$config = array(
	'unique_id' => elgg_get_site_url(),
	'delimiter' => '/',
	'directory' => $path['dirname'],
	'filename' => $_FILES['ical_file']['name']
);

$v = new vcalendar($config);
$v->parse();

$event_calendar_times = elgg_get_plugin_setting('times', 'event_calendar');
$event_calendar_region_display = elgg_get_plugin_setting('region_display', 'event_calendar');
$event_calendar_type_display = elgg_get_plugin_setting('type_display', 'event_calendar');
$event_calendar_more_required = elgg_get_plugin_setting('more_required', 'event_calendar');

// for now, turn off the more_required setting during import
elgg_set_plugin_setting('more_required', 'no', 'event_calendar');

$created = array(); // an array to hold all of the created events
while ($vevent = $v->getComponent()) {
  if ($vevent instanceof vevent) {
	$dtstart = $vevent->getProperty('dtstart');
	$dtend = $vevent->getProperty('dtend');
	$summary = $vevent->getProperty('summary');
	$description = $vevent->getProperty('description');
	$organiser = $vevent->getProperty('organizer', false, true);
	$venue = $vevent->getProperty('location') ? $vevent->getProperty('location') : "default";
					
	//cross plateform exchange
	$region = $fees = $type = $tags = $long_description = "";
	$region = $vevent->getProperty( 'X-PROP-REGION' );
	$fees = $vevent->getProperty( 'X-PROP-FEES' );
	$event_type = $vevent->getProperty( 'X-PROP-TYPE' );
	$tags = $vevent->getProperty( 'X-PROP-TAGS' );
	
	set_input('event_action', 'add_event');
	set_input('event_id', 0);
	
	if ($container_guid) {
	  set_input('group_guid', $container_guid);
	}
	
	set_input('title',$summary);
	set_input('venue',$venue);
	  
	if ($event_calendar_times == 'yes') {
	  set_input('start_time_h',$dtstart['hour']);
	  set_input('start_time_m',$dtstart['min']);
	  set_input('end_time_h',$dtend['hour']);
	  set_input('end_time_m',$dtend['min']);
	}
	
	$strdate = date('Y-m-d', mktime(0,0,0,$dtstart['month'],$dtstart['day'],$dtstart['year']));
	set_input('start_date',$strdate);					

	$enddate = date('Y-m-d', mktime(0,0,0,$dtend['month'],$dtend['day'],$dtend['year']));
	set_input('end_date',$enddate);
	
	set_input('brief_description',$description);
					
	if ($event_calendar_region_display == 'yes') {
	  set_input('region',$region);
	}
					
	if ($event_calendar_type_display == 'yes') {
	  set_input('event_type',$event_type);
	}
					
	set_input('fees',$fees);
	set_input('contact',$contact);
	set_input('organiser', $organiser['params']['CN']);
	set_input('tags',$tags);
	set_input('long_description',$long_description);
	
	$result = event_calendar_set_event_from_form(0, $container_guid);
	
	if ($result) {
	  $created[] = $result;
	  add_to_river('river/object/event_calendar/create','create',elgg_get_logged_in_user_guid(),$result->guid);
	}
	else {
	  $error = true;
	  break;
	}
  }
}

elgg_set_plugin_setting('more_required', $event_calendar_more_required, 'event_calendar');

if ($error) {
  // there was an error, lets undo the imports that may have happened so far
  if ($created) {
	foreach ($created as $new_event) {
	  $new_event->delete();
	}
  }
  register_error(elgg_echo('event_calendar_ical:error:failed'));
  forward(REFERER);
}

system_message(elgg_echo('event_calendar:add_event_response'));
forward(REFERER);