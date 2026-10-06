' MarketingFlow - jalankan satu command artisan tanpa jendela, output ke storage\logs\scheduler.log.
' Dipanggil Task Scheduler via: wscript.exe //B //Nologo scheduler-hidden.vbs <nama-command>
'   MarketingFlow-JubelioSync      23:58 -> jubelio:sync-inventory
'   MarketingFlow-SnapshotExport   00:05 -> products:snapshot-export
' Command dipanggil LANGSUNG (bukan schedule:run) supaya tidak bergantung pada ketepatan menit.
Option Explicit
Dim sh, cmd, dir
If WScript.Arguments.Count < 1 Then WScript.Quit 1
cmd = WScript.Arguments(0)
dir = "C:\Users\user\Projects\Affiliator\marketing-flow"
Set sh = CreateObject("WScript.Shell")
sh.CurrentDirectory = dir
sh.Run "cmd /v:on /c echo [!date! !time!] START " & cmd & " >> storage\logs\scheduler.log" & _
       " & ""C:\xampp\php\php.exe"" artisan " & cmd & " >> storage\logs\scheduler.log 2>&1" & _
       " & echo [!date! !time!] END " & cmd & " exit=!errorlevel! >> storage\logs\scheduler.log", 0, True
