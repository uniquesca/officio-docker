from time import sleep
import threading
import subprocess
import os
import sys
from subprocess import CalledProcessError

if sys.version_info[0] >= 3 and sys.version_info[0] < 2:
    print "You must use Python 2.X"
    sys.exit()

count = sys.argv[1]

pid = str(os.getpid())  
pidfile = "/tmp/rabbit.pid"

def worker(num, type):
	#type = 6|7; 6=cron; 7=user;
    print ("Worker: %s" % num)
    try:
        subprocess.check_call(r'curl -k --user-agent "Mozilla/4.0" https://secure.offcio.com.au/index/cron?p=%d'%type, shell=True)
    except CalledProcessError:
        os.unlink(pidfile)
        os.system('kill -9 ' + pid)

if os.path.isfile(pidfile):
    print "%s already exists, exiting" % pidfile
    sys.exit()
else:
    file(pidfile, 'w').write(pid)
    for i in range(int(count)):
        t = threading.Thread(target=worker, args=(i, 6,))
        t.start()
    #for i in range(2):
    #    t = threading.Thread(target=worker, args=(i, 7,))
    #    t.start()
    print "Press Ctrl+C to exit message retrieving"

	
try:
    user_input = input()
except KeyboardInterrupt:
    os.unlink(pidfile)
    sys.exit()   