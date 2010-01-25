<?php
/**
* copyright            : (C) 2001-2010 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

if (!($FUD_OPT_3 & 134217728)) {	// Calender is disabled.
	std_error('disabled');
}

$TITLE_EXTRA = ': {TEMPLATE: calendar_title}';

/* Draw a calendar.
 * This function is called from a template to inject a calender where it's needed.
 */
function draw_calendar($year, $month, $events = array(), $size = 'large', $highlight_y = '', $highlight_m = '', $highlight_d = '') {
	if ($size == 'large') {
		$weekdays = array('{TEMPLATE: sunday}','{TEMPLATE: monday}','{TEMPLATE: tuesday}','{TEMPLATE: wednesday}','{TEMPLATE: thursday}','{TEMPLATE: friday}','{TEMPLATE: saturday}');
	} else {
		$weekdays = array('{TEMPLATE: sunday_short}','{TEMPLATE: monday_short}','{TEMPLATE: tuesday_short}','{TEMPLATE: wednesday_short}','{TEMPLATE: thursday_short}','{TEMPLATE: friday_short}','{TEMPLATE: saturday_short}');
	}
	// MONDAY $weekdays = array('{TEMPLATE: monday}','{TEMPLATE: tuesday}','{TEMPLATE: wednesday}','{TEMPLATE: thursday}','{TEMPLATE: friday}','{TEMPLATE: saturday}', '{TEMPLATE: sunday}');

	/* Table headings. */
	$calendar = '<table cellpadding="0" cellspacing="0" class="calendar">';
	$calendar .= '<tr class="calendar-row"><td class="calendar-day-head">'.implode('</td><td class="calendar-day-head">',$weekdays).'</td></tr>';
	$calendar .= '<tr class="calendar-row">';

	/* Days and weeks vars. */
	$running_day = date('w', mktime(0, 0, 0, $month, 1, $year));
	// MONDAY $running_day = date('w', mktime(0, 0, 0, $month, 1, $year)) - 1;
	$days_in_month = date('t', mktime(0, 0, 0, $month, 1, $year));
	$days_in_this_week = 1;
	$day_counter = 0;

	/* Print "blank" days until the first of the current week. */
	for($x = 0; $x < $running_day; $x++) {
		$calendar .= '<td class="calendar-day-np">&nbsp;</td>';
		$days_in_this_week++;
	}

	/* Keep going with days. */
	for ($day = 1; $day <= $days_in_month; $day++) {
		if ($size == 'large') {
			$calendar .= '<td class="calendar-day"><div style="position:relative; height:100px;">';
		} else {
			$calendar .= '<td class="calendar-day"><div style="position:relative;">';
		}

		/* Add in the day number. */
		if ($year == $highlight_y && $month == $highlight_m && $day == $highlight_d) {
			$calendar .= '<div class="day-number"><b><i>*<a href="{TEMPLATE: day_cur_lnk}">'. $day .'</a></i></b></div>';
		} else {
			$calendar .= '<div class="day-number"><a href="{TEMPLATE: day_cur_lnk}">'. $day .'</a></div>';
		}

		$event_day = sprintf('%04d%02d%02d', $year, $month, $day);
		if (isset($events[$event_day])) {
			$event_count = 0;		
			foreach($events[$event_day] as $event) {
				if ($size == 'large') {
					$calendar .= '<div class="event">'. $event .'</div>';
				} else {
					$event_count++;
				}
			}
			if ($size != 'large' && $event_count) {
				$calendar .= '<div class="event">'. $event_count .'</div>';
			}
		} else {
			$calendar.= str_repeat('<p>&nbsp;</p>',2);
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

	/* Finish the rest of the days in the week. */
	if($days_in_this_week < 8) {
		for($x = 1; $x <= (8 - $days_in_this_week); $x++) {
			$calendar .= '<td class="calendar-day-np">&nbsp;</td>';
		}
	}

	/* Finalize and return calendar. */
	$calendar .= '</tr></table>';
	return $calendar;
}

/* Query events from database.
 */
function get_events($year, $month) {
	/* Fetch events to display from DB. */
	$events = array();
	$c = uq('SELECT u.alias, u.bday FROM {SQL_TABLE_PREFIX}users u WHERE bday LIKE \''. sprintf('%04d%02d', $year, $month) .'%\'');
	while ($r = db_rowarr($c)) {
		$events[$r[1]][] = 'Birthday: '. $r[0];
	}
	$c = uq('SELECT day, desc, link FROM {SQL_TABLE_PREFIX}calendar WHERE (month=\''.$month.'\' AND year=\''.$year.'\') OR (month=\'*\' AND year=\''.$year.'\') OR (month=\''.$month.'\' AND year=\'*\') || (month=\'*\' AND year=\'*\')');
	while ($r = db_rowarr($c)) {
		if (empty($r[2])) {
			$events[ sprintf('%04d%02d%02d', $year, $month, $r[0]) ][] = $r[1];
		} else {
			$events[ sprintf('%04d%02d%02d', $year, $month, $r[0]) ][] = '<a href="'. $r[2] .'">'. $r[1] .'</a>';
		}
	}

	return $events;
}

/*{PRE_HTML_PHP}*/
/*{POST_HTML_PHP}*/

/* Get calendar settings. */
$day   = isset($_GET['day'])   ? (int)$_GET['day']   : (int)date('d');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
$view  = isset($_GET['view'])  ? $_GET['view']  : 'm';	// Default to month view.
$months = array('{TEMPLATE: month_1}','{TEMPLATE: month_2}','{TEMPLATE: month_3}','{TEMPLATE: month_4}','{TEMPLATE: month_5}','{TEMPLATE: month_6}','{TEMPLATE: month_7}','{TEMPLATE: month_8}','{TEMPLATE: month_9}','{TEMPLATE: month_10}','{TEMPLATE: month_11}','{TEMPLATE: month_12}');

/* Build a 'month dropdown' that can be used in templates. */
$select_month_control = '<select name="month" id="month">';
for($x = 1; $x <= 12; $x++) {
	$select_month_control .= '<option value="'. $x .'"'. ($x != $month ? '' : ' selected="selected"') .'>'. date('F',mktime(0,0,0,$x,1,$year)) .'</option>';
}
$select_month_control .= '</select>';

/* Build a 'year dropdown' that can be used in templates. */
$year_range = 10;
$select_year_control = '<select name="year" id="year">';
for($x = ($year-floor($year_range/2)); $x <= ($year+floor($year_range/2)); $x++) {
	$select_year_control .= '<option value="'. $x .'"'. ($x != $year ? '' : ' selected="selected"') .'>'. $x .'</option>';
}
$select_year_control .= '</select>';

	
if ($view == 'y') {
	$next_year  = $year + 1;
	$prev_year  = $year - 1;
}

if ($view == 'm') {
	$next_year  = $month != 12 ? $year : $year + 1;
	$prev_year  = $month !=  1 ? $year : $year - 1;
	$next_month = $month != 12 ? $month + 1 : 1;
	$prev_month = $month !=  1 ? $month - 1 : 12;
	
	$events = get_events($year, $month);
}

if ($view == 'd') {
	$tomorrow = mktime(0, 0, 0, $month, $day+1, $year);
	$yesterday = mktime(0, 0, 0, $month, $day-1, $year);
	
	$next_day = date('d', $tomorrow);
	$prev_day = date('d', $yesterday);
	$next_month = date('m', $tomorrow);
	$prev_month = date('m', $yesterday);
	$next_year = date('Y', $tomorrow);
	$prev_year = date('Y', $yesterday);

	$events = get_events($year, $month);

	$event_day = sprintf('%04d%02d%02d', $year, $month, $day);
	$events_for_day = '';
	if (isset($events[$event_day])) {
		foreach($events[$event_day] as $event) {
			$events_for_day .= '{TEMPLATE: cal_entry}';
		}
	} else {
		$events_for_day .= '{TEMPLATE: cal_no_events}';
	}
	
}

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: CAL_PAGE}
