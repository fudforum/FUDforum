<?php
/*
    Copyright 2003,2004 Nils Knappmeier (nk@knappi.org)
    Copyright 2004-2024 Patrick R. Michaud (pmichaud@pobox.com)
    Copyright 2025-     Customized for FUDforum.

    This file implements a diff function in native PHP.  It is based
    upon the PHPDiffEngine code written by Nils Knappmeier, who in turn
    had used Daniel Unterberger's diff
    (http://www.holomind.de/phpnet/diff.php) as the basis for his code.
*/

/* PHPDiff returns the differences between $old and $new,
 * formatted in the standard diff(1) output format.
 */
function PHPDiff($old = '', $new = '')
{
   # Split the source text into arrays of lines.
   $t1 = preg_split('/\R/', $old);
   $x = array_pop($t1);
   if ($x > '') $t1[] = "$x\n\\ No newline at end of file";
   $t2 = preg_split('/\R/', strval($new));
   $x = array_pop($t2);
   if ($x > '') $t2[] = "$x\n\\ No newline at end of file";

   $t1_start = 0; $t1_end = count($t1);
   $t2_start = 0; $t2_end = count($t2);

   # Stop with a common ending.
   while ($t1_start < $t1_end && $t2_start < $t2_end
          && $t1[$t1_end-1] == $t2[$t2_end-1]) { $t1_end--; $t2_end--; }

   # Skip over any common beginning.
   while ($t1_start < $t1_end && $t2_start < $t2_end
          && $t1[$t1_start] == $t2[$t2_start]) { $t1_start++; $t2_start++; }

   # Build a reverse-index array using the line as key and line number as value.
   # Don't store blank lines, so they won't be targets of the shortest distance
   # search.
   for($i = $t1_start; $i < $t1_end; $i++) if ($t1[$i]>'') $r1[$t1[$i]][] = $i;
   for($i = $t2_start; $i < $t2_end; $i++) if ($t2[$i]>'') $r2[$t2[$i]][] = $i;

   $a1 = $t1_start; $a2 = $t2_start;   # start at beginning of each list
   $actions = array();

   # Walk this loop until we reach the end of one of the lists.
   while ($a1 < $t1_end && $a2 < $t2_end) {
     # If we have a common element, save it and go to the next.
     if ($t1[$a1] == $t2[$a2]) { $actions[] = 4; $a1++; $a2++; continue; }

     # Otherwise, find the shortest move (Manhattan-distance) from the
     # current location.
     $best1 = $t1_end; $best2 = $t2_end;
     $s1 = $a1; $s2 = $a2;
     while(($s1 + $s2 - $a1 - $a2) < ($best1 + $best2 - $a1 - $a2)) {
       $d = -1;
       foreach((array)@$r1[$t2[$s2]] as $n)
         if ($n >= $s1) { $d = $n; break; }
       if ($d >= $s1 && ($d + $s2 - $a1 - $a2) < ($best1 + $best2 - $a1 - $a2))
         { $best1 = $d; $best2 = $s2; }
       $d = -1;
       foreach((array)@$r2[$t1[$s1]] as $n)
         if ($n >= $s2) { $d = $n; break; }
       if ($d >= $s2 && ($s1 + $d - $a1 - $a2) < ($best1 + $best2 - $a1 - $a2))
         { $best1 = $s1; $best2 = $d; }
       $s1++; $s2++;
     }
     while ($a1 < $best1) { $actions[] = 1; $a1++; }  # deleted elements
     while ($a2 < $best2) { $actions[] = 2; $a2++; }  # added elements
  }

  # We've reached the end of one list, now walk to the end of the other.
  while($a1 < $t1_end) { $actions[] = 1; $a1++; }  # deleted elements
  while($a2 < $t2_end) { $actions[] = 2; $a2++; }  # added elements

  # And this marks our ending point
  $actions[] = 8;

  # Now, let's follow the path we just took and report the added/deleted
  # elements into $out.
  $op = 0;
  $x0 = $x1 = $t1_start; $y0 = $y1 = $t2_start;
  $out = array();
  foreach($actions as $act) {
    if ($act == 1) { $op |= $act; $x1++; continue; }
    if ($act == 2) { $op |= $act; $y1++; continue; }
    if ($op > 0) {
      $xstr = ($x1 == ($x0+1)) ? $x1 : ($x0+1) . ",$x1";
      $ystr = ($y1 == ($y0+1)) ? $y1 : ($y0+1) . ",$y1";
      if ($op == 1) $out[] = "{$xstr}d{$y1}";
      elseif ($op == 3) $out[] = "{$xstr}c{$ystr}";
      while ($x0 < $x1) { $out[] = '< ' . $t1[$x0]; $x0++; }   # deleted elems
      if ($op == 2) $out[] = "{$x1}a{$ystr}";
      elseif ($op == 3) $out[] = '---';
      while ($y0 < $y1) { $out[] = '> '.$t2[$y0]; $y0++; }   # added elems
    }
    $x1++; $x0 = $x1;
    $y1++; $y0 = $y1;
    $op = 0;
  }
  $out[] = '';
  return join("\n",$out);
}
?>
