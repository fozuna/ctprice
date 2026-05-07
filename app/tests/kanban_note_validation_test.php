<?php
// Validation test for note input trimming and ID presence
function isValidNote($id, $note) {
  if (!$id) return [false, 'ID ausente'];
  $n = trim((string)$note);
  if ($n === '') return [false, 'Observação vazia'];
  return [true, 'OK'];
}
$cases = [
  [1, 'texto válido'],
  [1, '   espaços antes  '],
  [1, "\n\ncom quebras\n"],
  [1, ''],
  [0, 'algum texto']
];
foreach ($cases as $c) {
  [$ok, $msg] = isValidNote($c[0], $c[1]);
  echo 'id='. $c[0] .' note="'.str_replace("\n","\\n",$c[1]).'" => '. ($ok?'OK':('FAIL: '.$msg)) . PHP_EOL;
}
