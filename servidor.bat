@echo off
rem =====================================================================
rem  VIP Imports - servidor de desenvolvimento
rem  A raiz do site deve ser a RAIZ DO PROJETO (nao usar -t public).
rem =====================================================================
cd /d "%~dp0"
echo.
echo  VIP Imports servindo em  http://localhost:8000
echo  Login de teste: maria@vip.app  /  senha123
echo  (Ctrl+C para parar)
echo.
php -S localhost:8000
