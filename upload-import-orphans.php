<?php
/**
 * Find files that where uploaded but not assigned, step 1
 * Shows a list of files found on the upload/ folder,
 * if they are allowed according to the sytem settings.
 * Files uploaded by the "Upload from computer" form also
 * remain on this folder until assigned to a client, so if
 * that form was not completed, the files can be imported
 * from here later on.
 * Submits an array of file names.
 *
 * @package ProjectSend
 * @subpackage Upload
 */
$footable = 1;
$allowed_levels = array(9,8,7);
require_once('sys.includes.php');

$active_nav = 'files';

$page_title = __('Find orphan files', 'cftp_admin');
include('header.php');

$database->MySQLDB();

/**
 * Use the folder defined on sys.vars.php
 * Composed of the absolute path to that file plus the
 * default uploads folder.
 */
$work_folder = UPLOADED_FILES_FOLDER;
?>

<div id="main">
	<h2><?php echo $page_title; ?></h2>

	<?php
		if ( false === CAN_UPLOAD_ANY_FILE_TYPE ) {
			$msg = __('This list only shows the files that are allowed according to your security settings. If the file type you need to add is not listed here, add the extension to the "Allowed file extensions" box on the options page.', 'cftp_admin');
			echo system_message('warning',$msg);
		}
	?>
	
	<?php
		/** Count clients to show an error message, or the form */
		$sql = $database->query("SELECT * FROM tbl_users WHERE level = '0'");
		$count = mysql_num_rows($sql);
		if (!$count) {
			/** Echo the "no clients" default message */
			message_no_clients();
		}
		else {
			/**
			 * Make a list of existing files on the database.
			 * When a file doesn't correspond to a record, it can
			 * be safely renamed.
			 */
			$sql = $database->query("SELECT url, id, public_allow FROM tbl_files WHERE public_allow='0'");
			$db_files = array();
			while($row = mysql_fetch_array($sql)) {
				$db_files[$row["url"]] = $row["id"];
			}

			/** Make an array of already assigned files */
			$sql = $database->query("SELECT DISTINCT file_id FROM tbl_files_relations");
			$assigned = array();
			while($row = mysql_fetch_array($sql)) {
				$assigned[] = $row["file_id"];
			}

			/** Read the temp folder and list every allowed file */
			if ($handle = opendir($work_folder)) {
				while (false !== ($filename = readdir($handle))) {
					$filename_path = $work_folder.'/'.$filename;
					if(!is_dir($filename_path)) {
						if ($filename != "." && $filename != "..") {
							/** Check types of files that are not on the database */							
							if (!array_key_exists($filename,$db_files)) {
								$file_object = new PSend_Upload_File();
								$new_filename = $file_object->safe_rename_on_disc($filename,$work_folder);
								/** Check if the filetype is allowed */
								if ($file_object->is_filetype_allowed($new_filename)) {
									/** Add it to the array of available files */
									$new_filename_path = $work_folder.'/'.$new_filename;
									//$files_to_add[$new_filename] = $new_filename_path;
									$files_to_add[] = array(
															'path'		=> $new_filename_path,
															'name'		=> $new_filename,
															'reason'	=> 'not_on_db',
														);
								}
							}
							else {
								/**
								 * These following files EXIST on DB ($db_files)
								 * but not on the assigned table ($assigned)
								 */
								if(!in_array($db_files[$filename],$assigned)) {
									$files_to_add[] = array(
															'path'		=> $filename_path,
															'name'		=> $filename,
															'reason'	=> 'not_assigned',
														);
								}
							}
						}
					}
				}
				closedir($handle);
			}
			
			if (!empty($_POST['search'])) {
				$search = mysql_real_escape_string($_POST['search']);
				
				function search_text($item) {
					global $search;
					if (stripos($item['name'], $search) !== false) {
						/**
						 * Items that match the search
						 */
						return true;
					}
					else {
						/**
						 * Remove other items
						 */
						unset($item);
					}
					return false;
				}

				$files_to_add = array_filter($files_to_add, 'search_text');
			}
			
//			var_dump($result);
			
			/**
			 * Generate the list of files if there is at least 1
			 * available and allowed.
			 */
			if(isset($files_to_add) && count($files_to_add) > 0) {
		?>

				<div class="form_actions_limit_results">
					<form action="" name="files_search" method="post" class="form-inline">
						<input type="text" name="search" id="search" value="<?php if(isset($_POST['search']) && !empty($_POST['search'])) { echo htmlspecialchars($_POST['search']); } ?>" class="txtfield form_actions_search_box" />
						<button type="submit" id="btn_proceed_search" class="btn btn-small"><?php _e('Search','cftp_admin'); ?></button>
					</form>
				</div>
				<div class="clear"></div>

				<form action="upload-process-form.php" name="upload_by_ftp" id="upload_by_ftp" method="post" enctype="multipart/form-data">
					<table id="add_files_from_ftp" class="footable" data-page-size="<?php echo FOOTABLE_PAGING_NUMBER; ?>">
						<thead>
							<tr>
								<th class="td_checkbox" data-sort-ignore="true">
									<input type="checkbox" name="select_all" id="select_all" value="0" />
								</th>
								<th data-sort-initial="true"><?php _e('File name','cftp_admin'); ?></th>
								<th data-type="numeric" data-hide="phone"><?php _e('File size','cftp_admin'); ?></th>
								<th data-type="numeric" data-hide="phone"><?php _e('Last modified','cftp_admin'); ?></th>
								<th data-hide="phone"><?php _e('Reason','cftp_admin'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
								foreach ($files_to_add as $add_file) {
									?>
										<tr>
											<td><input type="checkbox" name="add[]" value="<?php echo $add_file['name']; ?>" /></td>
											<td><?php echo $add_file['name']; ?></td>
											<td data-value="<?php echo filesize($add_file['path']); ?>"><?php echo format_file_size(filesize($add_file['path'])); ?></td>
											<td data-value="<?php echo filemtime($add_file['path']); ?>">
												<?php echo date(TIMEFORMAT_USE, filemtime($add_file['path'])); ?>
											</td>
											<td>
												<?php
													switch($add_file['reason']) {
														case 'not_on_db':
															_e('Never assigned to any user or group','cftp_admin');
															break;
														case 'not_assigned':
															_e('All assignations were removed','cftp_admin'); echo ' / '; _e('File is not public.','cftp_admin');
															break;
													}
												?>
											</td>
										</tr>
									<?php
								}
							?>
						</tbody>
					</table>

					<div class="pagination pagination-centered hide-if-no-paging"></div>

					<?php
						$msg = __('Please note that the listed files will be renamed if they contain invalid characters.','cftp_admin');
						echo system_message('info',$msg);
					?>
	
					<div class="after_form_buttons">
						<button type="submit" name="submit" class="btn btn-wide btn-primary" id="upload-continue"><?php _e('Continue','cftp_admin'); ?></button>
					</div>
				</form>
	
				<script type="text/javascript">
					$(document).ready(function() {
						$("#upload_by_ftp").submit(function() {
							var checks = $("td>input:checkbox").serializeArray(); 
							if (checks.length == 0) { 
								alert('<?php _e('Please select at least one file to proceed.','cftp_admin'); ?>');
								return false; 
							} 
						});

					});
				</script>
	
	<?php
			}
			else {
			/** No files found */
			?>
				<div class="whitebox whiteform whitebox_text">
					<p><?php _e('There are no files available to add right now.', 'cftp_admin'); ?></p>
					<p>
						<?php
							_e('To use this feature you need to upload your files via FTP to the folder', 'cftp_admin');
							echo ' <strong>'.$work_folder.'</strong>.';
						?>
					</p>
					<p><?php _e('This is the same folder where the files uploaded by the web interface will be stored. So if you finish uploading your files but do not assign them to any clients/groups, the files will still be there for later use.', 'cftp_admin'); ?></p>
				</div>
			<?php
			}
		} /** End if for users count */
	?>

</div>

<?php
	$database->Close();
	include('footer.php');
?>