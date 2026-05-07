@echo off
echo ========================================
echo    SISTEMA DE GESTAO DE METAS
echo    Madeplant - Acesso Rede Interna
echo ========================================
echo.

REM Obter o IP atual da máquina
for /f "tokens=2 delims=:" %%a in ('ipconfig ^| findstr /c:"IPv4"') do (
    for /f "tokens=1" %%b in ("%%a") do (
        set IP=%%b
        goto :found
    )
)

:found
echo IP atual da maquina: %IP%
echo.
echo URLs de acesso:
echo - Local: http://localhost:8000
echo - Rede interna: http://%IP%:8000
echo.
echo Compartilhe a URL da rede interna com sua equipe!
echo.
echo Pressione Ctrl+C para parar o servidor
echo ========================================
echo.

REM Iniciar o servidor PHP apontando para a pasta public
C:\xampp\php\php.exe -S 0.0.0.0:8000 -t public public\router.php

pause