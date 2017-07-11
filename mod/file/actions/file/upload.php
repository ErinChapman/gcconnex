<?php
/**
 * Elgg file uploader/edit action
 *
 * @package ElggFile
 */

// Get variables
$title = htmlspecialchars(get_input('title', '', false), ENT_QUOTES, 'UTF-8');
$title2 = htmlspecialchars(get_input('title2', '', false), ENT_QUOTES, 'UTF-8');
$title3 = gc_implode_translation($title,$title2);
$desc = get_input("description");
$desc2 = get_input("description2");
$desc3 = gc_implode_translation($desc,$desc2);
$access_id = (int) get_input("access_id");
$container_guid = (int) get_input('container_guid', 0);
$guid = (int) get_input('file_guid');
$tags = get_input("tags");

/// retrieve information whether this was marked as minor edit or not
$file_edit = get_input('minor_edit');

if ($container_guid == 0) {
	$container_guid = elgg_get_logged_in_user_guid();
}

elgg_make_sticky_form('file');

// check if upload attempted and failed
$uploaded_files = elgg_get_uploaded_files('upload');
$uploaded_file = array_shift($uploaded_files);
if ($uploaded_file && !$uploaded_file->isValid()) {
	$error = elgg_get_friendly_upload_error($uploaded_file->getError());
	register_error($error);
	forward(REFERER);
}

// check whether this is a new file or an edit
$new_file = true;
if ($guid > 0) {
	$new_file = false;
}

if ($new_file) {
	$file = new ElggFile();
	$file->subtype = "file";
} else {
	// load original file object
	$file = get_entity($guid);
	if (!$file instanceof ElggFile) {
		register_error(elgg_echo('file:cannotload'));
		forward(REFERER);
	}
	/* @var ElggFile $file */

	// user must be able to edit file
	if (!$file->canEdit()) {
		register_error(elgg_echo('file:noaccess'));
		forward(REFERER);
	}
}

if ($title3) {
	$file->title = $title3;
}
$file->description = $desc3;
$file->access_id = $access_id;
$file->container_guid = $container_guid;
$file->tags = string_to_tag_array($tags);

if ($uploaded_file && $uploaded_file->isValid()) {

	if ($file->acceptUploadedFile($uploaded_file)) {
		$guid = $file->save();
		/// execute this line of code only if cp_notifications is active and that file is not minor edit
		if (elgg_is_active_plugin('cp_notifications') && $file_edit != 1)
			elgg_trigger_event('single_file_upload', $file->getType(), $file);
		else
			elgg_unregister_event_handler('create','object','cp_create_notification');
	}
	
	if ($guid && $file->saveIconFromElggFile($file)) {
		$file->thumbnail = $file->getIcon('small')->getFilename();
		$file->smallthumb = $file->getIcon('medium')->getFilename();
		$file->largethumb = $file->getIcon('large')->getFilename();
	} else {
		$file->deleteIcon();
		unset($file->thumbnail);
		unset($file->smallthumb);
		unset($file->largethumb);
	}
} else if ($file->exists()) {
	$file->save();
}

// file saved so clear sticky form
elgg_clear_sticky_form('file');


// handle results differently for new files and file updates
if ($new_file) {
	if ($guid) {
		$message = elgg_echo("file:saved");
		system_message($message);
		elgg_create_river_item(array(
			'view' => 'river/object/file/create',
			'action_type' => 'create',
			'subject_guid' => elgg_get_logged_in_user_guid(),
			'object_guid' => $file->guid,
		));
	} else {
		// failed to save file object - nothing we can do about this
		$error = elgg_echo("file:uploadfailed");
		register_error($error);
	}

	$container = get_entity($container_guid);
	if (elgg_instanceof($container, 'group')) {
		forward("file/group/$container->guid/all");
	} else {
		forward("file/owner/$container->username");
	}

} else {
	if ($guid) {
		system_message(elgg_echo("file:saved"));
	} else {
		register_error(elgg_echo("file:uploadfailed"));
	}

	forward($file->getURL());
}
