<?php
    include(dirname(__FILE__)."/../config.php");
    include(dirname(__FILE__)."/functions.php");

    $entityBody = file_get_contents('php://input');
    $data = json_decode($entityBody, true);

    $res = array();
    $res["transfer"] = "basic";
    $res["hash_algo"] = "sha256";
    $res["objects"] = array();

    $allraws=raw_getalldata();
    $raws=array();
    foreach($allraws as $raw){
        if($raw['validated'] == "1") {
            $raws[$raw['checksum']."/".$raw['filesize']] = $raw['id'];
        }
    }

    foreach($data["objects"] as $object) {
        $key = $object['oid']."/".$object['size'];
        $id = $raws[$key];
        array_push($res["objects"],
            array(
            "oid"=> $object['oid'],
            "size"=> $object['size'],
            "actions" => array(
                "download"=> array(
                    "href"=> "http://".$_SERVER['SERVER_NAME']."/getfile.php?id=$id&type=raw",
                             )
                         )
            )
        );
    }

// [Sat Apr 05 13:05:12.984726 2025] [php:notice] [pid 282899:tid 282899] [client ::1:52030] array (
//   'operation' => 'download',
//   'objects' =>
//   array (
//     0 =>
//     array (
//       'oid' => 'c2ed9c01933f3136a6f886ae57fd59692ee5f565e4c07cc73087fb6bceaec80e',
//       'size' => 3386578,
//     ),
//   ),
//   'transfers' =>
//   array (
//     0 => 'lfs-standalone-file',
//     1 => 'basic',
//     2 => 'ssh',
//   ),
//   'ref' =>
//   array (
//     'name' => 'refs/heads/master',
//   ),
//   'hash_algo' => 'sha256',
// )



    // error_log(var_export($_SERVER, true));



    $out = json_encode($res);
    // error_log(var_export($out, true));
    header("Content-Type: application/vnd.git-lfs+json");
    echo($out);
