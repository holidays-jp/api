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
    const DIST = __DIR__ . '/../docs/v1';

    /**
     * holidaysJP constructor.
     * @param $url
     */
    public function __construct($url = null)
    {
        date_default_timezone_set('Asia/Tokyo');

        $this->ical_url = $url ?: 'https://calendar.google.com/calendar/ical/ja.japanese%23holiday%40group.v.calendar.google.com/public/basic.ics';
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

        $this->updateCheckedFile();
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

            // サマリ(祝日名)を求める
            if (preg_match('/SUMMARY:(.+?)\n/m', $event, $summary) != 1) {
                continue;
            }

            $results[$date->timestamp] = $this->convert_holiday_name($date, $summary[1]);
        }

        // 日付順にソートして返却
        ksort($results);
        return Collection::make($results);
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
        // todo
        return $holidays;
    }

    /**
     * APIデータをファイルに出力
     * @param Collection $data
     * @param string $year
     */
    function generate_api_file(Collection $data, string $year = '')
    {
        // 出力先フォルダがなければ作成
        $dist_dir = (!empty($year)) ? self::DIST . '/' . $year : self::DIST;
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

    protected function updateCheckedFile()
    {
        $checkedFile = self::DIST . '/checked_at.txt';
        file_put_contents($checkedFile, date('Y-m-d H:i:s'));
    }
}
