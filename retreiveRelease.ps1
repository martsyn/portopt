$scriptpath = $MyInvocation.MyCommand.Path
$dir = Split-Path $scriptpath

Push-Location $dir

$rlsDir = $dir + "\awsRelease"
$zipFile = $rlsDir + ".zip"

cp wrapper.js $rlsDir

docker cp quirky_bhaskara:/root/optLambda/build/Release/portopt.node $rlsDir

Add-Type -Assembly System.IO.Compression.FileSystem

rm $zipFile -ErrorAction Ignore

$compressionLevel = [System.IO.Compression.CompressionLevel]::Optimal
[System.IO.Compression.ZipFile]::CreateFromDirectory(
	$rlsDir, $zipFile, $compressionLevel, $false)
	
Pop-Location