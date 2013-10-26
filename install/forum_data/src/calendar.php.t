<?php
/**
* copyright            : (C) 2001-2013 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

/*{PRE_HTML_PHP}*/

if (!($FUD_OPT_3 & 134217728)) {	// Calender is disabled.
	std_error('disabled');
}

ses_update_status($usr->sid, '{TEMPLATE: cal_update}');

$TITLE_EXTRA = ': {TEMPLATE: calendar_title}';

/** Draw a calendar.
  * This function is called from a template to insert a calender where it's needed.
  */
function draw_calendar($year, $month, $size = 'large', $highlight_y = '', $highlight_m = '', $highlight_d = '') {
	// Full or abbreviated days.
	if ($size == 'large') {
		$weekdays = array('{TEMPLATE: sunday}','{TEMPLATE: monday}','{TEMPLATE: tuesday}','{TEMPLATE: wednesday}','{TEMPLATE: thursday}','{TEMPLATE: friday}','{TEMPLATE: saturday}');
	} else {
		$weekdays = array('{TEMPLATE: sunday_short}','{TEMPLATE: monday_short}','{TEMPLATE: tuesday_short}','{TEMPLATE: wednesday_short}','{TEMPLATE: thursday_short}','{TEMPLATE: friday_short}','{TEMPLATE: saturday_short}');
	}
	// WEEK START ON MONDAY: $weekdays = array('{TEMPLATE: monday}','{TEMPLATE: tuesday}','{TEMPLATE: wednesday}','{TEMPLATE: thursday}','{TEMPLATE: friday}','{TEMPLATE: saturday}', '{TEMPLATE: sunday}');

	// Get events for this month.
	$events = get_events($year, $month);

	// Table headings.
	$calendar = '<table cellpadding="0" cellspacing="0" class="calendar">';
	$calendar .= '<tr class="calendar-row"><td class="calendar-day-head">'. implode('</td><td class="calendar-day-head">', $weekdays).'</td></tr>';
	$calendar .= '<tr class="calendar-row">';

	// Days and weeks vars.
	$running_day = date('w', mktime(0, 0, 0, $month, 1, $year));
	// WEEK START ON MONDAY: $running_day = date('w', mktime(0, 0, 0, $month, 1, $year)) - 1;
	$days_in_month = date('t', mktime(0, 0, 0, $month, 1, $year));
	$days_in_this_week = 1;
	$day_counter = 0;

	// Print "blank" days until the first of the current week.
	for($x = 0; $x < $running_day; $x++) {
		$calendar .= '<td class="calendar-day-np">&nbsp;</td>';
		$days_in_this_week++;
	}

	// Keep going with days.
	for ($day = 1; $day <= $days_in_month; $day++) {
		if ($size == 'large') {
			$calendar .= '<td class="calendar-day"><div style="position:relative; height:100px;">';
		} else {
			$calendar .= '<td class="calendar-day"><div style="position:relative;">';
		}

		// Count events so we know if we need to link to the day.
		$event_day = sprintf('%04d%02d%02d', $year, $month, $day);
		$event_count = 0;
		if (isset($events[$event_day])) {
			foreach($events[$event_day] as $event) {
				$event_count++;
			}
		}
		
		// Add in the day number.
		$calendar .= '<div class="day-number">';
		if ($year == $highlight_y && $month == $highlight_m && $day == $highlight_d) {
			$calendar .= '<b><i>*</i></b>';
		}
		if ($event_count > 0) {
			$calendar .= '<a href="{TEMPLATE: day_cur_lnk}" rel="nofollow">'. $day .'</a>';
		} else {
			$calendar .= $day;
		}
		$calendar .= '</div>';

		// Add in events.
		if (isset($events[$event_day])) {
			if ($size == 'large') {
				foreach($events[$event_day] as $event) {
					$calendar .= '<div class="event">'. $event .'</div>';
				}
			} else {
				$calendar .= str_repeat('<p>&nbsp;</p>', 2);
			}
		} else {
			$calendar .= str_repeat('<p>&nbsp;</p>', 2);
		}

		$calendar .= '</div></td>';
		if ($running_day == 6) {
			$calendar .= '</tr>';
			if (($day_counter+1) != $days_in_month) {
				$calendar .= '<tr class="calendar-row">';
			}
			$running_day = -1;
			$days_in_this_week = 0;
		};
		$days_in_this_week++; $running_day++; $day_counter++;
	};

	// Finish the rest of the days in the week.
	if($days_in_this_week < 8) {
		for($x = 1; $x <= (8 - $days_in_this_week); $x++) {
			$calendar .= '<td class="calendar-day-np">&nbsp;</td>';
		}
	}

	// Finalize and return calendar.
	$calendar .= '</tr></table>';
	return $calendar;
}

/** Fetch events and birthdays from database. */
function get_events($year, $month, $day = 0) {
	$events = array();
	
	// Defined events.
	$c = uq('SELECT event_day, descr, link FROM {SQL_TABLE_PREFIX}calendar WHERE (event_month=\''. $month .'\' AND event_year=\''. $year .'\') OR (event_month=\'*\' AND event_year=\''. $year .'\') OR (event_month=\''. $month .'\' AND event_year=\'*\') OR (event_month=\'*\' AND event_year=\'*\')');
	while ($r = db_rowarr($c)) {
		if (empty($r[2])) {
			$events[ sprintf('%04d%02d%02d', $year, $month, $r[0]) ][] = $r[1];
		} else {
			$events[ sprintf('%04d%02d%02d', $year, $month, $r[0]) ][] = '<a href="'. $r[2] .'">'. $r[1] .'</a>';
		}
	}

	// Get list of birthdays (MMDDYYYY).
	if ($GLOBALS['FUD_OPT_3'] & 268435456) {
		// Number of birthdays per day of the month.
		if ($day == 0) {
			$c = uq('SELECT substr(birthday, 3, 2), count(*) FROM {SQL_TABLE_PREFIX}users WHERE birthday LIKE '. _esc(sprintf('%02d', $month) .'%') .' GROUP BY substr(birthday, 3, 2)');
			while ($r = db_rowarr($c)) {
				$dd        = $r[0];
				$birthdays = $r[1];
				$events[ $year . $month . $dd ][] = '{TEMPLATE: cal_birthdays}';
			}
		} else {
			// Full list of birthdays for a specific day.
			$c = uq('SELECT id, alias, birthday FROM {SQL_TABLE_PREFIX}users WHERE birthday LIKE '. _esc(sprintf('%02d%02d', $month, $day) .'%'));
			while ($r = db_rowarr($c)) {
				$yyyy = substr($r[2], 4);
				$mm   = substr($r[2], 0, 2);
				$dd   = substr($r[2], 2, 2);
				$age  = ($yyyy > 0) ? $year - $yyyy : 0;
				$user = '{TEMPLATE: cal_user_link}';
				$events[ $year . $mm . $dd ][] = '{TEMPLATE: cal_birthday}';
			}
		}
	}

	return $events;
}

/*{POST_HTML_PHP}*/

// Get calendar settings.
$day    = isset($_GET['day'])   ? (int)$_GET['day']   : (int)date('d');
$month  = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year   = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
$view   = isset($_GET['view'])  ? $_GET['view']  : 'm';	// Default to month view.
$months = array('{TEMPLATE: month_1}','{TEMPLATE: month_2}','{TEMPLATE: month_3}','{TEMPLATE: month_4}','{TEMPLATE: month_5}','{TEMPLATE: month_6}','{TEMPLATE: month_7}','{TEMPLATE: month_8}','{TEMPLATE: month_9}','{TEMPLATE: month_10}','{TEMPLATE: month_11}','{TEMPLATE: month_12}');
$cur_year = (int)date('Y');

// Build a 'month dropdown' that can be used in templates.
$select_month_control = '<select name="month" id="month">';
for($m = 1; $m <= 12; $m++) {
	$select_month_control .= '<option value="'. $m .'"'. ($m != $month ? '' : ' selected="selected"') .'>'. $months[ date('n', mktime(0,0,0,$m,1,$year)) - 1 ] .'</option>';
}
$select_month_control .= '</select>';

// Build a 'year dropdown' that can be used in templates.
$select_year_control = '<select name="year" id="year">';
for($x = $cur_year; $x < $cur_year+3; $x++) {
	$select_year_control .= '<option value="'. $x .'"'. ($x != $year ? '' : ' selected="selected"') .'>'. $x .'</option>';
}
$select_year_control .= '</select>';

// Navigation to next/previous days/months/years.
if ($view == 'y') {
	$next_year  = $year + 1;
	$prev_year  = $year - 1;
}

if ($view == 'm') {
	$next_year  = $month != 12 ? $year : $year + 1;
	$prev_year  = $month !=  1 ? $year : $year - 1;
	$next_month = $month != 12 ? $month + 1 : 1;
	$prev_month = $month !=  1 ? $month - 1 : 12;
}

if ($view == 'd') {
	$tomorrow  = mktime(0, 0, 0, $month, $day+1, $year);
	$yesterday = mktime(0, 0, 0, $month, $day-1, $year);
	
	$next_day   = date('d', $tomorrow);
	$prev_day   = date('d', $yesterday);
	$next_month = date('m', $tomorrow);
	$prev_month = date('m', $yesterday);
	$next_year  = date('Y', $tomorrow);
	$prev_year  = date('Y', $yesterday);

	$events = get_events($year, $month, $day);

	$event_day = sprintf('%04d%02d%02d', $year, $month, $day);
	$events_for_day = '';
	if (isset($events[$event_day])) {
		foreach($events[$event_day] as $event) {
			$events_for_day .= '{TEMPLATE: cal_event_entry}';
		}
	}
}

// Limit calendar to current year and 3 years in future.
// This is required to prevent bots from seeing an infinite number of pages.
if ($next_year >= $cur_year+3) $next_year = $next_month = $next_day = null;
if ($prev_year < $cur_year)    $prev_year = $prev_month = $prev_day = null;

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: CAL_PAGE}
