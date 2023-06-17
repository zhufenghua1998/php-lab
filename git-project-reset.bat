@echo off

::窗体描述

TITLE gitProjectReset Power By zhufenghua

::仓库路径

set /p gd=指定仓库的绝对路径（默认当前目录）：
if "%gd%" NEQ "" (echo 待处理的路径：%gd%)

::指定分支

set /p branchBat=请输入要处理的分支（默认main）：
if "%branchBat%"==""  (set branchBat=main)

::初始化日志

set /p gm=输入提交日志：
if "%gm%"==""  (set gm=清空历史提交记录，缩减仓库体积)

::是否压栈
if "%gd%" NEQ "" (
	pushd
	cd /d %dg%
)

::git命令

git checkout --orphan latest_branch
git add -A
git commit -am "%gm%"
git branch -D %branchBat%
git branch -m %branchBat%
git push -f origin %branchBat%
git pull origin %branchBat%

::git执行完毕
echo 已清除全部的历史记录!
echo 不会自动删除其他分支、不会删除当前分支的tag列表，如需删除请手动.
pause

::是否弹栈
if "%gd%" NEQ "" (
	popd
)