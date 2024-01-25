<?php
ob_start();
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . "/config/connectDB.php";
require_once __DIR__ . "/config/setting.php";
require_once __DIR__ . "/tools/crud.tool.php";
require_once __DIR__ . "/tools/function.tool.php";

date_default_timezone_set(Setting::$AppTimeZone);

$call = new TransferData();
$r    = $call->getTransfer();
print_r($r);
exit;

class TransferData
{
    public function getTransfer()
    {
        $lastId = $this->getLastIDInEbook();
        $row = $this->getNewDataFromJai($lastId);
        // return $row;
        if (!$row) {
            $this->sendLineNotify(0, 0);
            return 0;
        }
        $s = $this->insertNewDataToEbook($row);
        $f = $this->getLastModificationTimesByUniqueName();
        if (date('w') != 0 && date('w') != 6)
            $this->sendLineNotify($s, $f);
        return $s;
    }

    public function getLastIDInEbook()
    {
        $sql  = "SELECT id ";
        $sql .= "FROM asrs_error_trans ";
        $sql .= "WHERE 1=1 ";
        $sql .= "ORDER BY id desc ";
        $sql .= "LIMIT 1 ";

        try {
            $con = connect_database('ebooking');
            $obj = new CRUD($con);

            $result = $obj->customSelect($sql);

            if (empty($result))
                return 0;

            return $result['id'];
        } catch (PDOException $e) {
            return "Database connection failed: " . $e->getMessage();
        } catch (Exception $e) {
            return "An error occurred: " . $e->getMessage();
        } finally {
            $con = null;
        }
    }

    public function getNewDataFromJai($id)
    {
        $sql  = "SELECT * ";
        $sql .= "FROM asrs_error_trans ";
        $sql .= "WHERE id > $id ";
        $sql .= "ORDER BY id asc ";

        try {
            $con = connect_database('jai');
            $obj = new CRUD($con);

            $result = $obj->fetchRows($sql);

            if (empty($result))
                return false;
            return $result;
        } catch (PDOException $e) {
            return "Database connection failed: " . $e->getMessage();
        } catch (Exception $e) {
            return "An error occurred: " . $e->getMessage();
        } finally {
            $con = null;
        }
    }

    public function insertNewDataToEbook($row)
    {

        try {
            $con = connect_database('ebooking');
            $obj = new CRUD($con);

            foreach ($row as $k => $v) {
                $v = [
                    'id' => $v['id'],
                    'wh' => IsNullOrEmptyString($v['wh']) ? '' : $v['wh'],
                    'tran_date_time' => $v['tran_date_time'],
                    'Control_WCS' => IsNullOrEmptyString($v['Control WCS']) ? '' : $v['Control WCS'],
                    'Control_CELL' => IsNullOrEmptyString($v['Control CELL']) ? '' : $v['Control_CELL'],
                    'Machine' => IsNullOrEmptyString($v['Machine']) ? '' : $v['Machine'],
                    'Position' => IsNullOrEmptyString($v['Position']) ? '' : $v['Position'],
                    'Transport_Data_Total' => IsNullOrEmptyString($v['Transport_Data_Total']) ? '' : $v['Transport_Data_Total'],
                    'Error_Code' => IsNullOrEmptyString($v['Error Code']) ? '' : $v['Error Code'],
                    'Error_Name' => IsNullOrEmptyString($v['Error Name']) ? '' : $v['Error Name'],
                    'Transfer_Equipment' => IsNullOrEmptyString($v['Transfer Equipment #']) ? '' : $v['Transfer Equipment #'],
                    'Cycle' => IsNullOrEmptyString($v['Cycle']) ? '' : $v['Cycle'],
                    'Destination' => IsNullOrEmptyString($v['Destination']) ? '' : $v['Destination'],
                    'Final_Destination_Location' => IsNullOrEmptyString($v['Final Destination Location']) ? '' : $v['Final Destination Location'],
                    'Load_Size_Info_Height' => IsNullOrEmptyString($v['Load Size Info (Height)']) ? '' : $v['Load Size Info (Height)'],
                    'Load_Size_Info_Width' => IsNullOrEmptyString($v['Load Size Info (Width)']) ? '' : $v['Load Size Info (Width)'],
                    'Load_Size_Info_Length' => IsNullOrEmptyString($v['Load Size Info (Length)']) ? '' : $v['Load Size Info (Length)'],
                    'Load_Size_Info_Other' => IsNullOrEmptyString($v['Load Size Info (Other)']) ? '' : $v['Load Size Info (Other)'],
                    'Weight' => IsNullOrEmptyString($v['Weight']) ? '' : $v['Weight'],
                    'Barcode_Data' => IsNullOrEmptyString($v['Barcode Data']) ? '' : $v['Barcode Data'],
                ];


                $result = $obj->addRow($v, 'asrs_error_trans');
                // $result = $v;
                if (!is_numeric($result)) {
                    return $result;
                }
            }

            return $result;
        } catch (PDOException $e) {
            return "Database connection failed: " . $e->getMessage();
        } catch (Exception $e) {
            return "An error occurred: " . $e->getMessage();
        } finally {
            $con = null;
        }
    }

    public function sendLineNotify($err, $errFile)
    {
        $sToken    = MySecret::$l_token;
        if ($err == 0) {
            $sMessage  = " เช็คข้อมูลสำเร็จ\n";
            $sMessage .= "ไม่มีข้อมูลใหม่ในวันนี้";
        } else if (is_numeric($err)) {
            $sMessage  = " โอนถ่ายข้อมูลสำเร็จ\n";
            $sMessage .= "ID สุดท้ายคือ $err";
        } else {
            $sMessage  = " เกิดข้อผิดพลาด\n";
            $sMessage .= "ไม่สามารถโอนถ่ายข้อมูลได้\n";
            $sMessage .= "$err";
        }

        if ($errFile == 1){
            $sMessage .= "\nอัพเดตข้อมูลไฟล์ที่อัพโหลดสำเร็จ";
        } else if ($errFile == 0){

        } else {
            $sMessage .= "\nเกิดข้อผิดพลาดในการเช็คข้อมูลไฟล์\n";
            $sMessage .= "$errFile";
        }

        $chOne = curl_init();
        curl_setopt($chOne, CURLOPT_URL, "https://notify-api.line.me/api/notify");
        curl_setopt($chOne, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($chOne, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($chOne, CURLOPT_POST, 1);
        curl_setopt($chOne, CURLOPT_POSTFIELDS, "message=" . $sMessage);
        $headers = array('Content-type: application/x-www-form-urlencoded', 'Authorization: Bearer ' . $sToken . '',);
        curl_setopt($chOne, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($chOne, CURLOPT_RETURNTRANSFER, 1);

        try {
            $result = curl_exec($chOne);
            if ($result === false) {
                throw new Exception(curl_error($chOne));
            }
        } catch (Exception $e) {
            return 'Caught exception: ' . $e->getMessage();
        } finally {
            curl_close($chOne);
        }
    }

    public function getLastModificationTimesByUniqueName()
    {
        $folderPath = __DIR__ . "/" . Setting::$ErrorFilePath;
        $result = [];
        $this->readAllFilesByUniqueName($folderPath, $result);
        $r = $this->InsertToDB($result);
        return $r;
    }


    private function readAllFilesByUniqueName($folderPath, &$resultArray, $currentFolder = '')
    {
        $items = glob($folderPath . '/*');
        // $resultArray = $items;
        // return;
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if (is_file($item)) {
                $fileName = strtolower(basename($item)); // Convert filename to lowercase

                // Extract the unique name from the filename
                $matches = [];
                if (preg_match('/error_history_(.*?)\s/', $fileName, $matches)) {
                    $uniqueName = $matches[1];

                    // Check if the unique name is in the $Warehouse array
                    if (array_key_exists($uniqueName, Setting::$Warehouse)) {
                        // Get the last modification time
                        $modificationTime = filemtime($item);
                        $formattedTime = date('d.M.y H:i:s', $modificationTime);

                        // Check if we've already encountered this unique name
                        if (!isset($resultArray[$uniqueName])) {

                            $resultArray[$uniqueName] = [
                                'name' =>  $currentFolder,
                                'time' => $formattedTime,
                            ];
                        } else {
                            // If the current time is more recent, update it
                            if ($modificationTime > strtotime($resultArray[$uniqueName]['time'])) {
                                $resultArray[$uniqueName]['name'] = $currentFolder;
                                $resultArray[$uniqueName]['time'] = $formattedTime;
                            }
                        }
                    }
                }
            } elseif (is_dir($item)) {
                // This is a subfolder, so recurse into it with the updated folder name
                $this->readAllFilesByUniqueName($item, $resultArray, $currentFolder . '/' . basename($item));
            }
        }
    }

    private function InsertToDB($row){

        try {
            $con = connect_database('ebooking');
            $obj = new CRUD($con);

            foreach($row as $k => $v){
                $slashPosition = strpos($v['name'], '/');
                if ($slashPosition !== false) {
                    // ใช้ substr เพื่อลบตัวอักษรแรกที่เป็น "/"
                    $v['name'] = substr($v['name'], $slashPosition + 1);
                }

                if($this->haveID($k)){
                    $i = ['name'=> $v['name'], 'date'=> $v['time']];
                    $r = $obj->update($i, "wh='$k'", 'asrs_error_attachment');
                    if($r != 'Success'){
                        return $r;
                    }
                } else {
                    $i = ['wh'=> $k, 'name'=> $v['name'], 'date'=> $v['time']];
                    $r = $obj->addRow($i, 'asrs_error_attachment');
                    if(!is_numeric($r)){
                        return $r;
                    }
                }
            }

            return 1;

        } catch (PDOException $e) {
            return "Database connection failed: " . $e->getMessage();
        } catch (Exception $e) {
            return "An error occurred: " . $e->getMessage();
        } finally {
            $con = null;
        }
        
    }
    
    private function haveID($name){
        $sql  = "SELECT * ";
        $sql .= "FROM asrs_error_attachment ";
        $sql .= "WHERE wh='$name' ";

        try {
            $con = connect_database('ebooking');
            $obj = new CRUD($con);

            $r = $obj->customSelect($sql);

            if(!empty($r)){
                return true;
            }
            return false;

        } catch (PDOException $e) {
            return "Database connection failed: " . $e->getMessage();
        } catch (Exception $e) {
            return "An error occurred: " . $e->getMessage();
        } finally {
            $con = null;
        }
    }
}


