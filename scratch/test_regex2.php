<?php
$sql = 'INSERT INTO tbl_audit_log (audit_ref, created_at) VALUES (?, NOW());  ';
preg_match('/^\s*INSERT\s+INTO\s+([a-zA-Z0-9_`]+)\s*\((.*?)\)\s*VALUES\s*\((.*)\)\s*;?\s*$/i', $sql, $matches);
print_r($matches);
