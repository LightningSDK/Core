<?php

namespace lightningsdk\core\View\Field;

use DateTime;
use DateTimeZone;
use lightningsdk\core\Tools\ClientUser;
use lightningsdk\core\Tools\Request;
use lightningsdk\core\View\Field;

class Time extends Field {

    protected static $days = [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        7 => 'Sunday',
    ];

    /**
     * Get today's date on the JD calendar.
     *
     * @param DateTime $time
     *   A time object that might have a timezone set to return a different date time.
     *
     * @return integer
     *   The JD date of the server.
     */
    public static function today(DateTime $time = null) {
        if ($time) {
            return gregoriantojd($time->format('m'), $time->format('d'), $time->format('Y'));
        } else {
            return gregoriantojd(date('m'), date('d'), date('Y'));
        }
    }

    public static function jdtounix($jd) {
        $tz = new DateTimeZone(date_default_timezone_get() ?: 'UTC');
        $time = jdtounix($jd);
        $offset = $tz->getOffset(new DateTime("now", $tz));
        return $time + $offset;
    }

    /**
     * Create a string like 2 hours, 4 minutes, and 21 seconds.
     *
     * @param $time
     *   The time in seconds.
     *
     * @return string
     *   The formatted time.
     */
    public static function formatLength($time) {
        $seconds = $time % 60;
        $minutes = floor($time / 60) % 60;
        $hours = floor($time / 3600);

        if ($hours > 0) {
            return "$hours hours, $minutes minutes, and $seconds seconds";
        } elseif ($minutes > 0) {
            return "$minutes minutes and $seconds seconds";
        } else {
            return "$seconds seconds";
        }
    }

    public static function getDate($id, $allow_blank = true) {
        $m = Request::get($id ."_m");
        $d = Request::get($id ."_d");
        $y = Request::get($id ."_y");
        if ($m > 0 && $d > 0) {
            if ($y == 0) $y = date("Y");
            return gregoriantojd($m, $d, $y);
        } elseif (!$allow_blank) {
            return gregoriantojd(date("m"),date("d"),date("Y"));
        } else {
            return 0;
        }
    }

    public static function getDays() {
        return self::$days;
    }

    public static function printWeekday($day) {
        return self::$days[$day];
    }

    public static function getTimeZoneOffset($timezone) {
        if (empty($timezone)) {
            return 0;
        } else {
            if ($timezone == 'user') {
                $timezone = ClientUser::getInstance()->timezone;
                if (empty($timezone)) {
                    $timezone = date_default_timezone_get();
                }
                if (empty($timezone)) {
                    return 0;
                }
            }
            $tz = new DateTimeZone($timezone);
            $now = new DateTime('now', $tz);
            return $tz->getOffset($now);
        }
    }

    /**
     * Get the number of minutes into the day.
     *
     * @param integer $hours
     * @param integer $minutes
     * @param string $ap
     *
     * @return integer
     *   The number of minutes into the current day.
     */
    public static function getMinutes($hours, $minutes = 0, $ap = 'AM') {
        if ($hours == 12) {
            $hours = 0;
        }
        if (strtoupper($ap) == 'PM') {
            $hours += 12;
        }
        return ($hours * 60) + $minutes;
    }

    /**
     * Get an int time from the posted input.
     *
     * @param $id
     * @param bool $allow_blank
     * @param null $timezone
     * @return int
     */
    public static function getTime($id, $allow_blank = true, $timezone = null) {
        $h = Request::get($id .'_h', Request::TYPE_INT);
        $i = Request::get($id .'_i', Request::TYPE_INT);
        $a = Request::get($id .'_a');
        if (empty($h)) {
            if ($allow_blank) {
                return 0;
            } else {
                $time = explode('/', date('h/i/a', time()));
                $h = $time[0];
                $i = $time[1];
                $a = $time[2];
            }
        }

        // Get offset in minutes.
        $offset = self::getTimeZoneOffset($timezone) / 60;

        // Subtract the offset from the input time.
        $time = self::getMinutes($h, $i, $a) - $offset;

        // Normalize the time.
        $time = self::normalizeTime($time);

        return $time;
    }

    public static function getDateTime($id, $allow_blank = true, $timezone = null) {
        $m = Request::get($id . '_m', Request::TYPE_INT);
        $d = Request::get($id . '_d', Request::TYPE_INT);
        $y = Request::get($id . '_y', Request::TYPE_INT);
        $h = Request::get($id . '_h', Request::TYPE_INT);
        if ($h == 12) {
            $h = 0;
        }
        $i = str_pad(Request::get($id .'_i', Request::TYPE_INT), 2, 0, STR_PAD_LEFT);
        $h += Request::get($id . '_a', '', '', 'AM') == 'AM' ? 0 : 12;

        if ($allow_blank && (empty($m) || empty($d) || empty($y) || empty($h))) {
            return 0;
        }

        // Get offset.
        $offset = self::getTimeZoneOffset($timezone);

        // Subtract the offset from the input time.
        return gmmktime($h, $i, 0, $m, $d, $y) - $offset;
    }

    public static function normalizeTime($time) {
        while ($time > 0) {
            $time -= 60 * 24;
        }
        while ($time < 0) {
            $time += 60 * 24;
        }
        return $time;
    }

    public static function printDate($value) {
        if ($value == 0) {
            return '';
        }
        return jdtogregorian($value);
    }

    public static function printTime($value, $timezone = null) {
        if ($value == 0) {
            return '';
        }

        // Add the offset in minutes.
        $value += self::getTimeZoneOffset($timezone) / 60;
        $value = self::normalizeTime($value);

        $i = str_pad($value % 60, 2, 0, STR_PAD_LEFT);
        $h = ($value - $i) / 60;
        if ($h > 12) {
            $a = 'PM';
            $h -= 12;
        } else {
            $a = 'AM';
        }
        return "{$h}:{$i} {$a}";
    }

    public static function printDateTime($value, $timezone = null) {
        if (empty($value)) {
            return '';
        } else {
            $date = new Datetime('@' . $value);
            $date->setTimezone(new DateTimeZone($timezone));
            return $date->format('m/d/Y h:ia T');
        }
    }

    public static function datePop($field, $value, $allow_zero, $first_year = 0) {
        if (!$allow_zero && ($value == 0 || $value == '')) {
            $date = [date('m'), date('d'), date('Y')];
        } else {
            $date = explode('/', jdtogregorian($value));
        }
        $output = self::monthPop($field . '_m', $date[0], $allow_zero);
        $output .= ' / ';
        $output .= self::dayPop($field . '_d', $date[1], $allow_zero);
        $output .= ' / ';
        $output .= self::yearPop($field . '_y', $date[2], $allow_zero, $first_year);
        return $output;
    }

    public static function timePop($field, $value = null, $allow_zero = false, $timezone = null) {
        if (!$allow_zero && empty($value)) {
            $time = explode('/', date('h/i/a', time()));
            $h = $time[0];
            $i = $time[1];
            $a = $time[2];
            if ($a == 'PM') $h += 12;
            $value = ($h * 60) + $i;
        } else {
            $value += self::getTimeZoneOffset($timezone) / 60;
            self::normalizeTime($value);
            $i = $value % 60;
            $h = ($value - $i) / 60;
            if ($h > 12) {
                $a = 'PM';
                $h -= 12;
            } else {
                $a = 'AM';
            }
        }

        $output = self::hourPop($field . '_h', $h, $allow_zero) . ':';
        $output .= self::minutePop($field . '_i', empty($value) ? '' : $i, $allow_zero);
        $output .= ' ' . self::APPop($field . '_a', $a, $allow_zero);
        return $output;
    }

    public static function dateTimePop($field, $value, $allow_zero, $first_year = 0, $timezone = null) {
        if (!$allow_zero && empty($value)) {
            $value = time();
        }

        if (empty($value)) {
            $time = [0,0,0,0,0,0,0];
        } else {
            $value += self::getTimeZoneOffset($timezone);
            $date = new DateTime('@' . $value, new DateTimeZone('UTC'));
            $time = explode('/', $date->format('m/d/Y/h/i/s/a'));
        }
        $output = self::monthPop($field . "_m", $time[0], $allow_zero, ['class' => 'dateTimePop']) . ' / ';
        $output .= self::dayPop($field . "_d", $time[1], $allow_zero, ['class' => 'dateTimePop']) . ' / ';
        $output .= self::yearPop($field . "_y", $time[2], $allow_zero, $first_year, null, ['class' => 'dateTimePop']) . ' at ';
        $output .= self::hourPop($field . "_h", $time[3], $allow_zero, ['class' => 'dateTimePop']) . ':';
        $output .= self::minutePop($field . "_i", empty($value) ? null : $time[4], $allow_zero, ['class' => 'dateTimePop']) . ' ';
        $output .= self::APPop($field . "_a", $time[6], $allow_zero, ['class' => 'dateTimePop']);
        return $output;
    }

    public static function hourPop($field, $value = '', $allow_zero = false, $attributes = []) {
        $values = [];
        if ($allow_zero) {
            $values[''] = '';
        }
        $values += array_combine(range(1, 12), range(1, 12));

        // Set the default class.
        BasicHTML::setDefaultClass($attributes, 'timePop');

        // TODO: Pass the class into this renderer.
        return BasicHTML::select($field, $values, intval($value), $attributes);
    }

    /**
     * Build a popup selector for minutes.
     *
     * @param string $field
     *   The field name.
     * @param string $value
     *   The default value.
     * @param boolean $allow_zero
     *   Whether to allow the field to be blank.
     * @param array $attributes
     *   An array of attributes to add to the element..
     *
     * @return string
     *   The rendered HTML.
     */
    public static function minutePop($field, $value = '', $allow_zero = false, $attributes = []) {
        $values = array_combine(range(0, 9), range(0, 9));
        foreach ($values as &$value) {
            $value = '0' . $value;
        }
        $values += array_combine(range(10, 59), range(10, 59));
        if ($allow_zero) {
            $values = ['' => ''] + $values;
        }

        // Set the default class.
        BasicHTML::setDefaultClass($attributes, 'timePop');

        // TODO: Pass the class into this renderer.
        return BasicHTML::select($field, $values, intval($value), $attributes);
    }

    /**
     * Build a popup to select AM/PM
     *
     * @param string $field
     *   The field name.
     * @param string $value
     *   The default value.
     * @param boolean $allow_zero
     *   Whether to allow the field to be blank.
     * @param array $attributes
     *   An array of attributes to add to the element..
     *
     * @return string
     *   The rendered HTML
     */
    public static function APPop($field, $value = '', $allow_zero = false, $attributes = []) {
        $values = [];
        if ($allow_zero) {
            $values[''] = '';
        }
        $values += ['AM' => 'AM', 'PM' => 'PM'];

        // Set the default class.
        BasicHTML::setDefaultClass($attributes, 'timePop');

        // TODO: Pass the class into this renderer.
        return BasicHTML::select($field, $values, strtoupper($value), $attributes);
    }

    public static function dayPop($field, $day = 0, $allow_zero = false, $attributes = []) {
        $values = [];
        if ($allow_zero) {
            $values[''] = '';
        }
        $values += array_combine(range(1, 31), range(1, 31));

        // Set the default class.
        BasicHTML::setDefaultClass($attributes, 'datePop');

        // TODO: Pass the class into this renderer.
        return BasicHTML::select($field, $values, intval($day), $attributes);
    }

    public static function monthPop($field, $month = 0, $allow_zero = false, $attributes = []) {
        $values = [];
        if ($allow_zero) {
            $values[''] = '';
        }
        $info = cal_info(0);
        $values += $info['months'];

        // Set the default class.
        BasicHTML::setDefaultClass($attributes, 'datePop');

        // TODO: Pass the class into this renderer.
        return BasicHTML::select($field, $values, intval($month), $attributes);
    }

    public static function yearPop($field, $year = 0, $allow_zero = false, $first_year = null, $last_year = null, $attributes = []) {
        $values = [];
        if ($allow_zero) {
            $values[''] = '';
        }

        if (empty($first_year)) {
            $first_year = date('Y') - 1;
        }
        if (empty($last_year)) {
            $last_year = date('Y') + 10;
        }

        $values += array_combine(range($first_year, $last_year), range($first_year, $last_year));

        // Set the default class.
        BasicHTML::setDefaultClass($attributes, 'datePop');

        // TODO: Pass the class into this renderer.
        return BasicHTML::select($field, $values, intval($year), $attributes);
    }
}
