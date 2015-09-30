<!DOCTYPE html>
<html>
  <head>
    <style>
      thead {color:green;}
      tbody {color:blue;}
      tfoot {color:red;}
      table, th, td {
        border: 1px solid black;
      }
    </style>
  </head>
  <body>
<?php
  define('DRUPAL_ROOT', '/var/www/dnaindia.com/');
  $_SERVER['REMOTE_ADDR'] = "localhost"; // Necessary if running from command line
  require_once DRUPAL_ROOT . '/includes/bootstrap.inc';
  drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
 // put start time and end time
  $start_time = strtotime('2005-12-31 23:59:59');
  $end_time = strtotime('2006-01-31 23:59:59');
  print "<table>
  <thead>
    <tr>
      <th>SL No</th>
      <th>Nid</th>
      <th>Tag Names</th>
    </tr>
  </thead>
  <tbody>";

  $nids = get_all_nodes($start_time, $end_time);
  foreach($nids as $key => $nid) {
    $names = get_all_term_names_by_nid($nid);
    $terms = implode(", ",$names);
    $no = $key + 1;
//    print_r($names);die;
    print "<tr>
             <td>$no</td>
             <td>$nid</td>
             <td>$terms</td>
           </tr>";
  }

  print " </tbody>
        </table>";



/**
  *get all node nids
  */
function get_all_nodes($start_time, $end_time) {
  $query = "SELECT n.nid FROM {node} n JOIN field_data_field_date_published fp ON n.nid = fp.entity_id WHERE fp.field_date_published_value BETWEEN $start_time AND $end_time AND n.status = 1";
  $nids = db_query($query)->fetchCol();
  return $nids;
}


function get_all_term_names_by_nid($nid) {
  $query = "SELECT ttd.name FROM taxonomy_term_data AS ttd INNER JOIN taxonomy_index ti ON ti.tid = ttd.tid WHERE ti.nid= $nid and ttd.vid = 1 ORDER BY ttd.weight";
  $names = db_query($query)->fetchCol();
  
  return $names; 
}

?>
  </body>
</html>
