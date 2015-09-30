<?php
  define('DOMAIN', 'http://www.dnaindia.com/');
  define('DRUPAL_ROOT', '/var/www/dnaindia.com/');
  $_SERVER['REMOTE_ADDR'] = "localhost"; // Necessary if running from command line
  require_once DRUPAL_ROOT . '/includes/bootstrap.inc';
  drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

  require_once '/var/www/dnaindia.com/topics.php';

  //first node create time Mon, 08 Aug 2005 14:33:00 GMT
  //first nid = 49
  $start_time = variable_get('tag_api_time', strtotime('2005-12-31 23:59:59'));
  // get all nids  
  $nids = get_all_nodes($start_time);
  $total_nids = count($nids);
  print " ###  ".date('l j F Y H:i:s')."  ### \n" ;
  print       "##  Total no of nodes: $total_nids  ## \n";
  $close_time = microtime(true) +( 4 * 60 + 30); 
  $no = 0;
  foreach($nids as $nid) {
    print       "##  Node nid: $nid   ##\n";
    if(microtime(true) >= $close_time) {
      print "\n##### time end ########\n";
      break;
    }
    $no++;
    tag_api_processing($nid);
  }
 
  print       "\n##  $no  nodes out of : $total_nids updated ## \n";
  print       "##  Your process completed sucessfully  ## \n";
  print " ###  ".date('l j F Y H:i:s')."  ### \n" ;

function tag_api_processing($nid) {
  $node = node_load($nid);
//  print_R($node);die;
  $publish_time = $node->field_date_published['und'][0]['value'];
  $all_tids = array();
  if(isset($node->field_tags['und'])) {
    foreach($node->field_tags['und'] as $key => $value) {
      $all_tids[] = $value['tid'];
    }
  }
  // get all system generated tags
  $result = get_all_system_tags_by_nid($nid);
  foreach($result as $status_key => $tag_names) {
    analysize_api_tags($tag_names, $status_key, $all_tids, $node);
  }
  variable_set('tag_api_time', $publish_time);
}
/**
  * This function is used to verify api tags with cms tags and update its status 
  * $tag_names pass parameter as api tags
  * $tag_status mena its verified tags or unverified tag
  */
function analysize_api_tags($tag_names, $tag_status, $all_tids, $node) {
  $tag_status = ($tag_status == 'Verified') ? 'verified' : 'unverified'; 
  foreach($tag_names as $tag_name) {
    $terms = taxonomy_get_term_by_name($tag_name, 'tags');
    if(empty($terms)) {
      $node->field_tags['und'][]['tid'] = custom_create_taxonomy_term($tag_name, 1, $tag_status);
      continue;
    }
    foreach($terms as $key => $term) {
      $term->name = $tag_name;
      $term_id = $term->tid;
      if(!in_array($term_id ,$all_tids)) {
        $node->field_tags['und'][]['tid'] = $term->tid;
      }
      update_tag_status($term, 'base', $tag_status);
    }
  }

  api_node_save($node);
}

/**
  * update tags status in drupal cms
  * if tag already found in cms replace status to replace
  * else tag's status api or replace return it no need to update it
  */
function update_tag_status($term, $status, $tag_status) {
  if(isset($term->field_version['und'][0]['value']) && ($term->field_version['und'][0]['value'] == 'api' || $term->field_version['und'][0]['value'] == 'replace')) {
    return;
  }
 
  $term->field_type['und'][0]['value'] = $status;//field:  type
  $term->field_verified['und'][0]['value'] = $tag_status;//field: verified
  $term->field_version['und'][0]['value'] = 'replace';//field: version
  taxonomy_term_save($term);
}

/**
 * Create a taxonomy term with status as api and return the tid.
 */
function custom_create_taxonomy_term($name, $vid, $tag_status) {
  $term = new stdClass();
  $term->name = $name;
  $term->vid = $vid;
  $term->field_type['und'][0]['value'] = 'base';//field: type
  $term->field_verified['und'][0]['value'] = $tag_status;//field: verified
  $term->field_version['und'][0]['value'] = 'api';//field: version
  taxonomy_term_save($term);
  return $term->tid;
}
/**
  *get all node nids
  */
function get_all_nodes($start_time) {
  $query = "SELECT n.nid FROM {node} n JOIN field_data_field_date_published fp ON n.nid = fp.entity_id WHERE fp.field_date_published_value > $start_time  AND n.status = 1  and n.nid != 1019762 order by fp.field_date_published_value asc limit 300;";
  $nids = db_query($query)->fetchCol();
  return $nids;
}

/**
  * get all system generated tags by nid
  */
function get_all_system_tags_by_nid($nid) {
  $full_path = get_full_path_by_nid($nid);
  $topics = new Topics();
  $text = decode_entities(strip_tags($full_path));
  $params = array('link' => $text );
  $results = $topics->tagByLink($params);

  return $results;
}

function get_full_path_by_nid($nid) {
  $alias = drupal_get_path_alias('node/'.$nid);
  $full_path = DOMAIN.$alias;
  return $full_path;
}

function api_node_save($node) {
  $transaction = db_transaction();

  try {
    // Load the stored entity, if any.
    if (!empty($node->nid) && !isset($node->original)) {
      $node->original = entity_load_unchanged('node', $node->nid);
    }

    field_attach_presave('node', $node);
    global $user;

    // Determine if we will be inserting a new node.
    if (!isset($node->is_new)) {
      $node->is_new = empty($node->nid);
    }
/*
    // Set the timestamp fields.
    if (empty($node->created)) {
      $node->created = REQUEST_TIME;
    }
    // The changed timestamp is always updated for bookkeeping purposes,
    // for example: revisions, searching, etc.
    $node->changed = REQUEST_TIME;

    $node->timestamp = REQUEST_TIME;
*/
    $update_node = TRUE;

    // Let modules modify the node before it is saved to the database.
    module_invoke_all('node_presave', $node);
    module_invoke_all('entity_presave', $node, 'node');

    if ($node->is_new || !empty($node->revision)) {
      // When inserting either a new node or a new node revision, $node->log
      // must be set because {node_revision}.log is a text column and therefore
      // cannot have a default value. However, it might not be set at this
      // point (for example, if the user submitting a node form does not have
      // permission to create revisions), so we ensure that it is at least an
      // empty string in that case.
      // @todo: Make the {node_revision}.log column nullable so that we can
      // remove this check.
      if (!isset($node->log)) {
        $node->log = '';
      }
    }
    elseif (!isset($node->log) || $node->log === '') {
      // If we are updating an existing node without adding a new revision, we
      // need to make sure $node->log is unset whenever it is empty. As long as
      // $node->log is unset, drupal_write_record() will not attempt to update
      // the existing database column when re-saving the revision; therefore,
      // this code allows us to avoid clobbering an existing log entry with an
      // empty one.
      unset($node->log);
    }

    // When saving a new node revision, unset any existing $node->vid so as to
    // ensure that a new revision will actually be created, then store the old
    // revision ID in a separate property for use by node hook implementations.
    if (!$node->is_new && !empty($node->revision) && $node->vid) {
      $node->old_vid = $node->vid;
      unset($node->vid);
    }

    // Save the node and node revision.
    if ($node->is_new) {
      // For new nodes, save new records for both the node itself and the node
      // revision.
      drupal_write_record('node', $node);
      _node_save_revision($node, $user->uid);
      $op = 'insert';
    }
    else {
      // For existing nodes, update the node record which matches the value of
      // $node->nid.
      drupal_write_record('node', $node, 'nid');
      // Then, if a new node revision was requested, save a new record for
      // that; otherwise, update the node revision record which matches the
      // value of $node->vid.
      if (!empty($node->revision)) {
        _node_save_revision($node, $user->uid);
      }
      else {
        _node_save_revision($node, $user->uid, 'vid');
        $update_node = FALSE;
      }
      $op = 'update';
    }
    if ($update_node) {
      db_update('node')
        ->fields(array('vid' => $node->vid))
        ->condition('nid', $node->nid)
        ->execute();
    }

    // Call the node specific callback (if any). This can be
    // node_invoke($node, 'insert') or
    // node_invoke($node, 'update').
    node_invoke($node, $op);

    // Save fields.
    $function = "field_attach_$op";
    $function('node', $node);

    module_invoke_all('node_' . $op, $node);
    module_invoke_all('entity_' . $op, $node, 'node');

    // Update the node access table for this node.
    node_access_acquire_grants($node);

    // Clear internal properties.
    unset($node->is_new);
    unset($node->original);
    // Clear the static loading cache.
    entity_get_controller('node')->resetCache(array($node->nid));

    // Ignore slave server temporarily to give time for the
    // saved node to be propagated to the slave.
    db_ignore_slave();
  }
  catch (Exception $e) {
    $transaction->rollback();
    watchdog_exception('node', $e);
    throw $e;
  }
}





?>
