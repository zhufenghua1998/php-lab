@echo off

::��������

TITLE gitProjectReset Power By zhufenghua

::�ֿ�·��

set /p gd=ָ���ֿ�ľ���·����Ĭ�ϵ�ǰĿ¼����
if "%gd%" NEQ "" (echo �������·����%gd%)

::ָ����֧

set /p branchBat=������Ҫ����ķ�֧��Ĭ��main����
if "%branchBat%"==""  (set branchBat=main)

::��ʼ����־

set /p gm=�����ύ��־��
if "%gm%"==""  (set gm=�����ʷ�ύ��¼�������ֿ����)

::�Ƿ�ѹջ
if "%gd%" NEQ "" (
	pushd
	cd /d %dg%
)

::git����

git checkout --orphan latest_branch
git add -A
git commit -am "%gm%"
git branch -D %branchBat%
git branch -m %branchBat%
git push -f origin %branchBat%
git pull origin %branchBat%

::gitִ�����
echo �����ȫ������ʷ��¼!
echo �����Զ�ɾ��������֧������ɾ����ǰ��֧��tag�б�����ɾ�����ֶ�.
pause

::�Ƿ�ջ
if "%gd%" NEQ "" (
	popd
)