$WshShell = New-Object -ComObject WScript.Shell
$Shortcut = $WshShell.CreateShortcut("$env:USERPROFILE\Desktop\Logycab.lnk")
$Shortcut.TargetPath = "C:\Program Files\Google\Chrome\Application\chrome.exe"
$Shortcut.Arguments = "--app=http://localhost/logycab/splash.php --window-size=1280,800"
$Shortcut.Description = "Logycab — Cabinet de Cardiologie"
$Shortcut.Save()
Write-Host "Raccourci cree sur le bureau." -ForegroundColor Green
