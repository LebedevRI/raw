#!/usr/bin/php
<?php
    include("../config.php");
    include("../www/functions.php");

    // found on http://php.net/manual/en/function.rmdir.php
    function delTree($dir) {
       $files = array_diff(scandir($dir), array('.','..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? delTree("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
      }

    function writeGitLFSPointer($filename, $raw) {
        $fp=fopen($filename,"w");
        fprintf($fp,"version https://git-lfs.github.com/spec/v1\n",);
        fprintf($fp,"oid sha256:%s\n", $raw['checksum']);
        fprintf($fp,"size %u\n", $raw['filesize']);
        fclose($fp);
    }
    function turnIntoAGitLFSRepo($cwd) {

        $env = array();
        proc_close(proc_open(array('git', 'init', '--bare', '--initial-branch=master'), null, null, $cwd, $env));
        proc_close(proc_open(array('git', 'add', '.'), null, null, $cwd, $env));
        proc_close(proc_open(array('git', 'commit', ''), null, null, $cwd, $env));

        $fp=fopen($cwd."/.gitattributes","w");
        fprintf($fp,"* filter=lfs diff=lfs merge=lfs -text",);
        fprintf($fp,".gitattributes !filter !diff !merge text",);
        fprintf($fp,"timestamp.txt !filter !diff !merge text",);
        fprintf($fp,"filelist.sha256 !filter !diff !merge text",);
        fclose($fp);

        proc_close(proc_open(array('git', 'add', '.gitattributes'), null, null, $cwd, $env));
        proc_close(proc_open(array('git', 'commit', '--amend'), null, null, $cwd, $env));
    }

    $cameradata=parsecamerasxml();
    $data=raw_getalldata();
    $makes=array();
    $noncc0samples=0;

    if(is_dir(publicdatapath)){
        delTree(publicdatapath);
    }
    mkdir(publicdatapath);

    if(is_dir(publicdatapath_git)){
        delTree(publicdatapath_git);
    }
    mkdir(publicdatapath_git);

    foreach($data as $raw){
        if($raw['validated']==1){
            $make="unknown";
            $model="unknown";
            if($raw['make']!=""){
                $make=$cameradata[$raw['make']][$raw['model']]['make'] ?? $cameradata[$raw['make']]['make'] ?? $raw['make'];
            }
            if($raw['model']!=""){
                $model=$cameradata[$raw['make']][$raw['model']]['model'] ?? $raw['model'];
            }
            $make = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $make);
            $model = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $model);
            if(!is_dir(publicdatapath."/".$make)){
                mkdir(publicdatapath."/".$make);
            }
            if(!is_dir(publicdatapath."/".$make."/".$model)){
                mkdir(publicdatapath."/".$make."/".$model);
            }
            if(!is_dir(publicdatapath_git."/".$make)){
                mkdir(publicdatapath_git."/".$make);
            }
            if(!is_dir(publicdatapath_git."/".$make."/".$model)){
                mkdir(publicdatapath_git."/".$make."/".$model);
            }
            $output_filename=$make."/".$model."/".$raw['filename'];
            symlink(datapath."/".hash_id($raw['id'])."/".$raw['id']."/".$raw['filename'], publicdatapath."/".$output_filename);
            writeGitLFSPointer(publicdatapath_git."/".$output_filename, $raw);
            $sha256table[$make."/".$model."/".$raw['filename']]=$raw['checksum'];

            if(!in_array($make,$makes)){
                $makes[]=$make;
            }

            if($raw['license']!="CC0"){
                $noncc0samples++;
            }
        }
    }

    ksort($sha256table, SORT_NATURAL | SORT_FLAG_CASE);

    $fp=fopen(publicdatapath."/filelist.sha256","w");
    foreach($sha256table as $file=>$sha256) {
        // There are two schemes:
        // <hash><space><space><filename>      <- read in text mode
        // <hash><space><asterisk><filename>   <- read in binary mode
        fprintf($fp,"%s *%s\n",$sha256,$file);
    }
    fclose($fp);

    copy(publicdatapath."/filelist.sha256", publicdatapath_git."/filelist.sha256");

    file_put_contents(publicdatapath."/timestamp.txt",time());
    copy(publicdatapath."/timestamp.txt", publicdatapath_git."/timestamp.txt");

    turnIntoAGitRepo(publicdatapath_git);

    // Badgegeneration
    $cameras=raw_getnumberofcameras();
    file_put_contents("../www/button-cameras.svg", file_get_contents("https://img.shields.io/badge/cameras-".$cameras."-green.svg?maxAge=3600"));
    file_put_contents("../www/button-cameras.png", file_get_contents("https://img.shields.io/badge/cameras-".$cameras."-green.png?maxAge=3600"));
    file_put_contents("../www/button-makes.svg", file_get_contents("https://img.shields.io/badge/makes-".count($makes)."-green.svg?maxAge=3600"));
    file_put_contents("../www/button-makes.png", file_get_contents("https://img.shields.io/badge/makes-".count($makes)."-green.png?maxAge=3600"));
    $samples=raw_getnumberofsamples();
    file_put_contents("../www/button-samples.svg", file_get_contents("https://img.shields.io/badge/samples-".$samples."-green.svg?maxAge=3600"));
    file_put_contents("../www/button-samples.png", file_get_contents("https://img.shields.io/badge/samples-".$samples."-green.png?maxAge=3600"));
    $reposize=raw_gettotalrepositorysize();
    file_put_contents("../www/button-size.svg", file_get_contents("https://img.shields.io/badge/size-".human_filesize($reposize)."-green.svg?maxAge=3600"));
    file_put_contents("../www/button-size.png", file_get_contents("https://img.shields.io/badge/size-".human_filesize($reposize)."-green.png?maxAge=3600"));

    $reposize/=(1024*1024*1024);

    $missingcameras=count(unserialize(file_get_contents(datapath."/missingcameradata.serialize")));

    $postdata="rpu,key=cameras value=$cameras\n";
    $postdata.="rpu,key=samples value=$samples\n";
    $postdata.="rpu,key=reposize value=$reposize\n";
    $postdata.="rpu,key=noncc0samples value=$noncc0samples\n";
    $postdata.="rpu,key=missingcameras value=$missingcameras\n";
    $postdata.="rpu,key=makes value=".count($makes)."\n";

    $opts = array('http' => array( 'method'  => 'POST', 'header'  => "Content-Type: application/x-www-form-urlencoded\r\n", 'content' => $postdata, 'timeout' => 60 ) );
    $context  = stream_context_create($opts);
    $url = influxserver."/write?db=".influxdb;
    file_get_contents($url, false, $context);

