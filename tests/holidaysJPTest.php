<?php

use Cake\Chronos\Chronos;
use HolidaysJP\holidaysJP;
use PHPUnit\Framework\TestCase;


/**
 * Class holidaysJPTest
 */
class holidaysJPTest extends TestCase
{
    private $dist = __DIR__ . '/tmp';

    /**
     * ical解析関連のテスト
     */
    public function testICALAnalyze()
    {
        // サンプル ical データの解析テスト
        $test_file = __DIR__ . '/data/testdata.ics';
        $holidays = new holidaysJP($test_file);
        $data = $holidays->get_holidays($holidays->get_ical_data());

        $expected = [
            1420038000 => '元日',
            1458486000 => '春分の日 振替休日',
            1513954800 => '天皇誕生日',
        ];
        $this->assertEquals($expected, $data->toArray());
    }

    /**
     * ファイル生成に関するテスト
     */
    public function testGenerator()
    {
        // 実際のデータの生成
        $url = 'https://calendar.google.com/calendar/ical/japanese__ja@holiday.calendar.google.com/public/full.ics';
        $holidays = new holidaysJP($url, $this->dist);
        $holidays->generate();

        // 一覧データのチェック
        $year = Chronos::now()->year;
        $this->checkApiFile('date.json', $year);
        $this->checkApiFile('datetime.json', $year, true);
        $this->checkApiFile('date.csv', $year);
        $this->checkApiFile('datetime.csv', $year, true);

        // 年別データのチェック (今年)
        $this->checkApiFile("{$year}/date.json", $year);
        $this->checkApiFile("{$year}/datetime.json", $year, true);
        $this->checkApiFile("{$year}/date.csv", $year);
        $this->checkApiFile("{$year}/datetime.csv", $year, true);

        // 年別データのチェック (来年)
        $nextyear = $year + 1;
        $this->checkApiFile("{$nextyear}/date.json", $nextyear);
        $this->checkApiFile("{$nextyear}/datetime.json", $nextyear, true);
        $this->checkApiFile("{$nextyear}/date.csv", $nextyear);
        $this->checkApiFile("{$nextyear}/datetime.csv", $nextyear, true);

        // 2024/2025 ファイル一致チェック
        $file1 = file_get_contents(__DIR__ . '/data/2024.json');
        $file2 = file_get_contents("{$this->dist}/2024/date.json");
        $this->assertEquals($file1, $file2);

        $file1 = file_get_contents(__DIR__ . '/data/2025.json');
        $file2 = file_get_contents("{$this->dist}/2025/date.json");
        $this->assertEquals($file1, $file2);
    }

    /**
     * APIファイルが存在し、データが入っているか
     * @param $filename
     * @param $year
     * @param bool $is_datetime
     */
    private function checkApiFile($filename, $year, bool $is_datetime = false)
    {
        $data = array();

        // ファイルの存在チェック
        $filename = "{$this->dist}/{$filename}";

        $this->assertFileExists($filename);

        $fileChkArr = explode(".", $filename);
        $fileExtension = end($fileChkArr);
        $allowExtensions = array('json', 'csv');

        $this->assertContains($fileExtension, $allowExtensions);
        if ($fileExtension == 'json') {
            $data = json_decode(file_get_contents($filename), true);
        } else {
            $csvArrByLine = array();
            $recordArr = array();

            // $csvArrByLine =
            //     [0] => "YYYY-mm-dd,holiday", ...
            $csvArrByLine = str_getcsv(file_get_contents($filename), "\n");

            // $recordArr =
            //     [0] => Array(
            //        [0] => YYYY-mm-dd,
            //        [1] => holiday
            //     ),...
            // $data = Array(
            //     ["YYYY-mm-dd"] => holiday,...,
            foreach($csvArrByLine as $key => $csvLine) {
                $recordArr[] = str_getcsv($csvLine);
                $date = $recordArr[$key][0];
                $text = $recordArr[$key][1];
                $data[$date] = $text;
            }
        }

        // 元日のデータが入っているか
        $dt = Chronos::createFromDate($year)->startOfYear();
        $key = ($is_datetime) ? $dt->timestamp : $dt->toDateString();
        $this->assertArrayHasKey($key, $data, $filename);
    }
}
