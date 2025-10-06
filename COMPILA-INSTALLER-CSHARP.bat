@echo off
REM FP Digital Marketing Suite - Compilazione Installer C# Diretto
REM Questo script compila direttamente un installer .exe in C#

title FP DMS - Compilazione Installer C# Diretto

echo ========================================
echo FP DIGITAL MARKETING SUITE
echo COMPILAZIONE INSTALLER C# DIRETTO
echo ========================================
echo.
echo Questo script compila direttamente un installer .exe
echo usando C# e .NET Framework!
echo.

REM Crea directory di lavoro
if exist "build\installer-csharp" (
    echo Pulendo build precedente...
    rmdir /s /q build\installer-csharp
)

echo Creando directory di lavoro...
mkdir build\installer-csharp
cd build\installer-csharp

echo.
echo ========================================
echo STEP 1: Verifica .NET Framework
echo ========================================
echo.

REM Verifica se .NET Framework è installato
reg query "HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\NET Framework Setup\NDP\v4\Full\" /v Release >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo ERRORE: .NET Framework 4.0 o superiore non trovato!
    echo.
    echo Scarica e installa .NET Framework da:
    echo https://dotnet.microsoft.com/download/dotnet-framework
    echo.
    pause
    exit /b 1
)

echo .NET Framework trovato!
echo.

echo.
echo ========================================
echo STEP 2: Creazione progetto C#
echo ========================================
echo.

echo Creando progetto C# installer...
mkdir FPDMSInstaller
cd FPDMSInstaller

REM Crea file di progetto
echo Creando file di progetto...
(
echo ^<Project Sdk="Microsoft.NET.Sdk"^>
echo.
echo   ^<PropertyGroup^>
echo     ^<OutputType^>WinExe^</OutputType^>
echo     ^<TargetFramework^>net48^</TargetFramework^>
echo     ^<UseWindowsForms^>true^</UseWindowsForms^>
echo     ^<AssemblyTitle^>FP Digital Marketing Suite Installer^</AssemblyTitle^>
echo     ^<AssemblyDescription^>Installer professionale per FP Digital Marketing Suite^</AssemblyDescription^>
echo     ^<AssemblyCompany^>Francesco Passeri^</AssemblyCompany^>
echo     ^<AssemblyProduct^>FP Digital Marketing Suite^</AssemblyProduct^>
echo     ^<AssemblyCopyright^>Copyright © Francesco Passeri 2024^</AssemblyCopyright^>
echo     ^<AssemblyVersion^>1.0.0.0^</AssemblyVersion^>
echo     ^<FileVersion^>1.0.0.0^</FileVersion^>
echo     ^<ApplicationIcon^>^</ApplicationIcon^>
echo     ^<PublishSingleFile^>true^</PublishSingleFile^>
echo     ^<SelfContained^>true^</SelfContained^>
echo     ^<RuntimeIdentifier^>win-x64^</RuntimeIdentifier^>
echo   ^</PropertyGroup^>
echo.
echo   ^<ItemGroup^>
echo     ^<PackageReference Include="System.IO.Compression" Version="4.3.0" /^>
echo     ^<PackageReference Include="System.IO.Compression.ZipFile" Version="4.3.0" /^>
echo   ^</ItemGroup^>
echo.
echo ^</Project^>
) > FPDMSInstaller.csproj

REM Copia il file C# sorgente
echo Copiando codice sorgente...
copy ..\..\..\CREA-INSTALLER-CSHARP.cs Program.cs

echo.
echo ========================================
echo STEP 3: Compilazione
echo ========================================
echo.

REM Verifica se MSBuild è disponibile
where msbuild >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo MSBuild non trovato, provo con dotnet...
    
    REM Verifica se dotnet CLI è disponibile
    where dotnet >nul 2>nul
    if %ERRORLEVEL% NEQ 0 (
        echo ERRORE: Né MSBuild né dotnet CLI trovati!
        echo.
        echo Installa Visual Studio Build Tools o .NET SDK da:
        echo https://dotnet.microsoft.com/download
        echo.
        pause
        exit /b 1
    )
    
    echo Compilando con dotnet CLI...
    dotnet publish -c Release -r win-x64 --self-contained true -p:PublishSingleFile=true
    
    if %ERRORLEVEL% NEQ 0 (
        echo ERRORE: Compilazione fallita!
        pause
        exit /b 1
    )
    
    REM Rinomina il file compilato
    if exist "bin\Release\net48\win-x64\publish\FPDMSInstaller.exe" (
        move "bin\Release\net48\win-x64\publish\FPDMSInstaller.exe" "..\FP-DMS-Installer.exe"
        echo Installer .exe creato con successo!
    ) else (
        echo ERRORE: File compilato non trovato!
        pause
        exit /b 1
    )
    
) else (
    echo Compilando con MSBuild...
    msbuild FPDMSInstaller.csproj /p:Configuration=Release /p:Platform="Any CPU" /p:PublishProfile=FolderProfile
    
    if %ERRORLEVEL% NEQ 0 (
        echo ERRORE: Compilazione fallita!
        pause
        exit /b 1
    )
    
    REM Rinomina il file compilato
    if exist "bin\Release\FPDMSInstaller.exe" (
        move "bin\Release\FPDMSInstaller.exe" "..\FP-DMS-Installer.exe"
        echo Installer .exe creato con successo!
    ) else (
        echo ERRORE: File compilato non trovato!
        pause
        exit /b 1
    )
)

cd ..

echo.
echo ========================================
echo STEP 4: Verifica e Pulizia
echo ========================================
echo.

REM Verifica che l'installer sia stato creato
if exist "FP-DMS-Installer.exe" (
    for %%A in (FP-DMS-Installer.exe) do set size=%%~zA
    set /a sizeMB=%size%/1048576
    
    echo ========================================
    echo INSTALLER .EXE CREATO CON SUCCESSO!
    echo ========================================
    echo.
    echo File creato: FP-DMS-Installer.exe
    echo Dimensione: %sizeMB% MB
    echo Percorso: %CD%\FP-DMS-Installer.exe
    echo.
    echo ========================================
    echo CARATTERISTICHE DELL'INSTALLER
    echo ========================================
    echo.
    echo ✓ Installer Windows Forms professionale
    echo ✓ Interfaccia grafica moderna
    echo ✓ Download automatico PHP Desktop
    echo ✓ Configurazione automatica applicazione
    echo ✓ Database SQLite embedded
    echo ✓ Interfaccia moderna e intuitiva
    echo ✓ Collegamento desktop automatico
    echo ✓ Nessuna conoscenza tecnica richiesta
    echo ✓ Installazione in Program Files
    echo ✓ Progress bar e feedback visivo
    echo ✓ Gestione errori completa
    echo.
    echo ========================================
    echo ISTRUZIONI PER L'UTENTE FINALE
    echo ========================================
    echo.
    echo 1. Doppio click su FP-DMS-Installer.exe
    echo 2. Clicca "Installa" nella finestra che si apre
    echo 3. L'installer scaricherà e configurerà tutto automaticamente
    echo 4. Al termine, avvia l'applicazione dal desktop
    echo 5. Login con: admin@localhost / admin123
    echo.
    echo ========================================
    echo PRONTO PER LA DISTRIBUZIONE!
    echo ========================================
    
    REM Crea README per distribuzione
    echo ======================================== > README.txt
    echo FP DIGITAL MARKETING SUITE - INSTALLER >> README.txt
    echo ======================================== >> README.txt
    echo. >> README.txt
    echo INSTALLAZIONE: >> README.txt
    echo 1. Eseguire FP-DMS-Installer.exe >> README.txt
    echo 2. Cliccare "Installa" nella finestra >> README.txt
    echo 3. L'installer scaricherà e configurerà tutto automaticamente >> README.txt
    echo 4. Al termine, avviare l'applicazione dal desktop >> README.txt
    echo. >> README.txt
    echo PRIMO ACCESSO: >> README.txt
    echo - Email: admin@localhost >> README.txt
    echo - Password: admin123 >> README.txt
    echo - IMPORTANTE: Cambiare la password dopo il primo accesso! >> README.txt
    echo. >> README.txt
    echo CARATTERISTICHE: >> README.txt
    echo ✓ Installer Windows Forms professionale >> README.txt
    echo ✓ Interfaccia grafica moderna >> README.txt
    echo ✓ Download automatico di tutti i componenti >> README.txt
    echo ✓ Configurazione automatica >> README.txt
    echo ✓ Database SQLite embedded >> README.txt
    echo ✓ Interfaccia moderna e intuitiva >> README.txt
    echo ✓ Completamente portable >> README.txt
    echo ✓ Nessuna conoscenza tecnica richiesta >> README.txt
    echo ✓ Installazione in Program Files >> README.txt
    echo ✓ Progress bar e feedback visivo >> README.txt
    echo ✓ Gestione errori completa >> README.txt
    echo. >> README.txt
    echo REQUISITI: >> README.txt
    echo - Windows 7 o superiore >> README.txt
    echo - Connessione internet per il primo download >> README.txt
    echo - 100MB di spazio libero >> README.txt
    echo. >> README.txt
    echo SUPPORTO: >> README.txt
    echo - Email: info@francescopasseri.com >> README.txt
    echo - Web: https://francescopasseri.com >> README.txt
    echo. >> README.txt
    echo LICENZA: >> README.txt
    echo GPLv2 o successiva >> README.txt
    
    echo Documentazione creata: README.txt
    
    REM Pulizia
    echo Pulendo file temporanei...
    rmdir /s /q FPDMSInstaller
    
) else (
    echo ERRORE: Installer .exe non trovato!
    echo Controlla i log di compilazione per errori.
    pause
    exit /b 1
)

echo.
echo ========================================
echo PROCESSO COMPLETATO!
echo ========================================
echo.
echo L'installer .exe è pronto per la distribuzione!
echo.
echo File creati:
echo - FP-DMS-Installer.exe (installer professionale)
echo - README.txt (documentazione)
echo.
echo L'installer è completamente autonomo e non richiede
echo alcuna conoscenza tecnica agli utenti finali!
echo.
pause
