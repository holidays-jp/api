<?php

namespace HolidaysJP;

use Cake\Chronos\Chronos;
use Illuminate\Support\Collection;

/**
 * Class holidaysJP
 * @package HolidaysJP
 */
class holidaysJP
{
    protected $ical_url;
    protected $dist;

    /**
     * holidaysJP constructor.
     * @param $url
     */
    public function __construct($url = null, $dist = null)
    {
        date_default_timezone_set('Asia/Tokyo');

        $this->ical_url = $url ?: 'https://calendar.google.com/calendar/ical/ja.japanese%23holiday%40group.v.calendar.google.com/public/basic.ics';
        $this->dist = $dist ?: dirname(__DIR__) . '/docs/v1';
    }

    /**
     * APIファイル生成 メイン処理
     */
    public function generate()
    {
        // 祝日データを取得
        $holidays = $this->get_holidays();

        // 一覧データを出力
        $this->generate_api_file($this->filter_for_3years($holidays));

        // データを年別に分解
        $yearly = $holidays->groupBy(function ($item, $key) {
                return Chronos::createFromTimestamp($key)->year;
            }, true);

        // 年別データを出力
        foreach ($yearly as $year => $a) {
            $this->generate_api_file($a, $year);
        }
    }

    /**
     * iCalデータの取得 (+ 不要文字などの削除)
     * @return array|false|string|string[]
     */
    function get_ical_data()
    {
        $ics = file_get_contents($this->ical_url);
        return str_replace("\r", '', $ics);
    }

    /**
     * iCal形式のデータを配列に変換
     * @param $data
     * @return Collection
     */
    function get_holidays($data = null): Collection
    {
        $data = $data ?? $this->get_ical_data();

        $results = [];

        // イベントごとに区切って配列化
        $events = explode('END:VEVENT', $data);

        foreach ($events as $event) {
            // 日付を求める
            if (preg_match('/DTSTART;\D*(\d+)/m', $event, $m) != 1) {
                continue;
            }
            $date = Chronos::createFromTimestamp(strtotime($m[1]));

            // 祝日名を取得
            $holiday_name = $this->get_holiday_name($event);
            if (!$holiday_name) {
                continue;
            }

            $results[$date->timestamp] = $this->convert_holiday_name($date, $holiday_name);
        }

        // 日付順にソートして返却
        ksort($results);
        return Collection::make($results);
    }

    public function get_holiday_name($event): ?string
    {
        if (preg_match('/SUMMARY:(.+?)\n/m', $event, $summary) != 1) {
            return null;
        };

        return $this->filetr_holiday_name($summary[1]);
    }

    public function filetr_holiday_name($name): ?string
    {
        $holidays = [
            '元日', '成人の日', '建国記念の日', '天皇誕生日',
            '春分の日',	'昭和の日',	'憲法記念日', 'みどりの日',
            'こどもの日', '海の日',	'山の日', '敬老の日',
            '秋分の日',	'スポーツの日',	'文化の日',	'勤労感謝の日',
            '国民の休日', '祝日',
            // 過去の祝日
            '体育の日', '天皇の即位の日', '即位礼正殿の儀の行われる日',
        ];
        foreach ($holidays as $holiday) {
            if (strpos($name, $holiday) !== false) {
                return $name;
            }
        }
        return null;
    }

    /**
     * @param Chronos $date
     * @param $name
     * @return string
     */
    public function convert_holiday_name(Chronos $date, $name): string
    {
        if ($name == '体育の日' && $date->year >= 2020) {
            return 'スポーツの日';
        }
        if ($name == 'スポーツの日' && $date->year <= 2019) {
            return '体育の日';
        }

        return $name;
    }

    /**
     * 去年・今年・来年のデータのみに絞る
     * @param Collection $holidays
     * @return Collection
     */
    public function filter_for_3years(Collection $holidays): Collection
    {
        $range = [
            'start' => Chronos::now()->subYears(1)->startOfYear()->timestamp,
            'end'   => Chronos::now()->addYears(1)->endOfYear()->timestamp
        ];

        return $holidays->filter(function($name, $timestamp) use ($range) {
            return $timestamp >= $range['start'] && $timestamp <= $range['end'];
        });
    }

    /**
     * APIデータをファイルに出力
     * @param Collection $data
     * @param string $year
     */
    function generate_api_file(Collection $data, string $year = '')
    {
        // 出力先フォルダがなければ作成
        $dist_dir = (!empty($year)) ? "{$this->dist}/{$year}" : $this->dist;
        if (!is_dir($dist_dir)) {
            mkdir($dist_dir);
        }

        // ファイル出力 (datetime型)
        $this->output_json_file($dist_dir . "/datetime.json", $data);
        $this->output_csv_file($dist_dir . "/datetime.csv", $data);

        // キーをYMD形式に変換して出力
        $date_data = $data->keyBy(function ($item, $key) {
            return Chronos::createFromTimestamp($key)->toDateString();
        });

        // ファイル出力 (date)
        $this->output_json_file($dist_dir . "/date.json", $date_data);
        $this->output_csv_file($dist_dir . "/date.csv", $date_data);
    }


    /**
     * JSONファイルを出力
     * @param $filename
     * @param $data
     */
    protected function output_json_file($filename, $data)
    {
        file_put_contents($filename, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /**
     * CSVファイルを出力
     * @param $filename
     * @param $data
     */
    protected function output_csv_file($filename, $data)
    {
        $recordArr = array();

        foreach ($data as $date => $text) {
            $recordArr[] = [$date, $text];
        }
        $fp = fopen($filename, 'w');
        foreach ($recordArr as $record) {
            fputcsv($fp, $record);
        }
        fclose($fp);
    }
}
