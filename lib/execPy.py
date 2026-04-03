###LIB_EXECPY=26.01-07
import time, sys, json, warnings, os, tempfile
import random, re, socket, subprocess

from urllib.parse import urlparse, quote 
from pathlib import Path
warnings.filterwarnings('ignore')
os.environ["PYTHONWARNINGS"] = "ignore"


def log(msg): print(msg, file=sys.stderr)


# =========================
# DRIVER
try:
    from seledroid import webdriver
    from seledroid.webdriver.common.by import By
except ImportError:
    log("install Seledroid module and Apk")
    sys.exit(1) 


def make_driver(ua=None):
    d = webdriver.Chrome(gui=True, pip_mode=True)
    #d = webdriver.Chrome(gui=False)
    if PX:
        log(f"DBG seledroid proxy => {PX[0]}:{PX[1]}")
        d.set_proxy(PX[0], PX[1])
    if ua:
        d.user_agent = ua
    return d


def driver_dead(err: Exception) -> bool:
    s = str(err).lower()
    return (
        "broken pipe" in s or
        "errno 32" in s or
        "applicationclosed" in s or
        "please close me by driver.close" in s or
        "time out to receive data" in s
    )

def driver_retry(fn, url=None, ua=None, cookie_file=None, retries=3):
    last = None
    for _ in range(retries):
        d = make_driver(ua=ua)
        try:
            if url:
                init_session(d, url, ua=None, cookie_file=cookie_file)  # UA sudah diset di make_driver
            return fn(d)
        except Exception as e:
            last = e
            if driver_dead(e):
                log(f"retry: {e}")
                continue
            raise
        finally:
            try: d.close()
            except: pass
    raise RuntimeError(f"driver failed: {last}")





# =========================
# INIT
def init_session(driver, url, ua=None, cookie_file=None):
    # set UA
    if ua:
        try:
            driver.user_agent = ua
        except Exception as e:
            log(f"set user_agent failed: {e}")

    u = urlparse(url)
    origin = f"{u.scheme}://{u.netloc}/"

    # init domain context
    driver.get(origin)

    # inject cookies
    if cookie_file:
        for name, value in load_netscape(cookie_file, url):
            try:
                driver.set_cookie(name, value, url=origin)
            except Exception:
                pass

    return origin

def load_netscape(cookie_file, url):
    if not cookie_file or not os.path.exists(cookie_file):
        return []

    u = urlparse(url)
    host = (u.hostname or "").lower()
    now = int(time.time())

    cookies = []
    with open(cookie_file, "r", encoding="utf-8", errors="ignore") as f:
        for line in f:
            line = line.strip()
            if not line:
                continue

            if line.startswith("#") and not line.startswith("#HttpOnly_"):
                continue

            cols = line.split("\t")
            if len(cols) < 7:
                continue

            domain, include_sub, path, secure, expires, name, value = cols[:7]
            if domain.startswith("#HttpOnly_"):
                domain = domain[len("#HttpOnly_"):]

            # expiry check
            try:
                exp = int(expires)
                if exp != 0 and exp < now:
                    continue
            except:
                pass

            dom = domain.lower().lstrip(".")
            if not (host == dom or host.endswith("." + dom)):
                continue

            cookies.append((name, value))

    return cookies












# =========================
# HELPER
def reactor(driver):
    try:
        if random.random() < 1:
            scroll_amt = random.randint(80, 220)
            y = random.randint(350, 650)
            if random.random() > 0.5:
                driver.swipe(120, y, 120, y - scroll_amt, speed=1)
            else:
                driver.swipe(120, y, 120, y + scroll_amt, speed=1)

        if random.random() < 0.8:
            driver.click_java(random.randint(50, 260), random.randint(80, 320))

    except Exception as e:
        log(f"reactor failed: {e}")


def check_ua(ua=None):
    driver = make_driver(ua=ua)
    driver.get("https://httpbin.org/user-agent")
    time.sleep(2)

    src = driver.page_source or ""
    driver.close()

    m = re.search(r'"user-agent"\s*:\s*"([^"]+)"', src, flags=re.I)
    if m:
        return m.group(1)

    src2 = re.sub(r"<[^>]+>", "", src).strip()
    return src2

def start_ser(port=8000):
    docroot = os.path.dirname(os.path.realpath(__file__))
    p = subprocess.Popen(
        ["python", "-m", "http.server", str(port)],
        cwd=docroot,
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL
    )

    for _ in range(40):
        try:
            with socket.create_connection(("127.0.0.1", port), timeout=0.2):
                return p
        except Exception:
            time.sleep(0.1)

    try:
        p.terminate()
    except Exception:
        pass
    raise RuntimeError("failed to start")

def stop_ser(p):
    try:
        p.terminate()
        p.wait(timeout=2)
    except Exception:
        try:
            p.kill()
        except Exception:
            pass




# INTERSTITIAL
def interstitial(url, ua=None):
    def job(driver):
        log(f"capturing interstitial: {url}")
        driver.get(url)
        time.sleep(5)

        def wait_cookie(max_wait=100, interval=1):
            clearance = None
            waf = None
            elapsed = 0.0
            tick = 0

            while elapsed < max_wait:
                try:
                    #reactor(driver)
                    clearance = driver.get_cookie("cf_clearance")
                    waf = driver.get_cookie("waf_what_a_faucet_session")
                except Exception as e:
                    if driver_dead(e):
                        raise
                if clearance:
                    log("cf_clearance obtained")
                    break

                time.sleep(interval)
                elapsed += interval
                tick += 1

            return clearance, waf

        clearance, waf = wait_cookie(max_wait=100, interval=0.3)

        # reload
        if not clearance:
            log("reload last attempt")
            driver.get(url)
            time.sleep(3)
            clearance, waf = wait_cookie(max_wait=30, interval=0.3)

        try:
            uagent = driver.user_agent
        except Exception:
            uagent = None

        try:
            cookies_all = driver.get_cookies()
        except Exception as e:
            if driver_dead(e):
                raise
            cookies_all = []


        return {"waf": waf, "cf_clearance": clearance, "user_agent": uagent, "cookie": cookies_all}

    return driver_retry(job, url=None, ua=ua, retries=3)


# TURNSTILE 
def turnstile(url, ua=None, cookie_file=None):
    def job(driver):
        log(f"capturing turnstile: {url}")
        driver.get(url)
        reactor(driver)

        def wait_token(max_wait=60):
            token = None
            for i in range(max_wait):
                try:
                    el = driver.find_element(By.NAME, "cf-turnstile-response")
                    val = (el.get_attribute("value") or "").strip()
                    if val and len(val) > 20:
                        token = val
                        break
                except Exception as e:
                    if driver_dead(e):
                        raise

                if i and i % 10 == 0:
                    log("waiting token...")
                time.sleep(1)
            return token

        token = wait_token(max_wait=60)

        if not token:
            log("reload last attempt")
            driver.get(url)
            reactor(driver)
            token = wait_token(max_wait=30)

        return {"token": token}

    return driver_retry(job, url=url, ua=ua, cookie_file=cookie_file, retries=3)


# RECAPTCHA v3
def recaptcha3(url, action, ua=None, cookie_file=None):
    def job(driver):
        log(f"capturing recaptcha3 on: {url}")
        init_session(driver, url, ua=None, cookie_file=cookie_file)

        driver.get(url)
        token = None
        sitekey = None

        try:
            scripts = driver.find_elements(By.CSS_SELECTOR, 'script[src*="recaptcha"]')
            for script in scripts:
                src = script.get_attribute("src")
                if src and "render=" in src:
                    sitekey = src.split("render=")[1].split("&")[0]
                    break

            if not sitekey:
                elements = driver.find_elements(By.CSS_SELECTOR, ".g-recaptcha")
                for el in elements:
                    val = el.get_attribute("data-sitekey")
                    if val:
                        sitekey = val
                        break
        except Exception:
            sitekey = None

        if sitekey:
            for i in range(50):
                try:
                    token = driver.get_recaptcha_v3_token(action=action)
                    if token and len(token) > 50:
                        break
                except Exception as e:
                    if driver_dead(e):
                        raise
                    if i % 10 == 0:
                        log("still waiting")
                    time.sleep(0.6)

        return {"sitekey": sitekey, "token": token}

    return driver_retry(job, url=url, ua=ua, cookie_file=cookie_file, retries=3)


# FINGERPRINT
def build(ua=None):
    server = None
    driver = make_driver(ua=ua)

    try:
        server = start_ser(port=8000)
        url = "http://127.0.0.1:8000/ip.html"

        log("capturing fingerprint")
        driver.get(url)
        time.sleep(5)

        last_reload = time.time()
        for _ in range(60):
            els = driver.find_elements(By.ID, "out")
            if els:
                txt = els[0].get_attribute("textContent")
                s = str(txt).strip() if txt is not None else ""
                if s and s not in ("initialize", "running..."):
                    try:
                        # bersihkan string agar JSON valid
                        clean = txt.strip().replace("\\n", "\n")
                        return json.loads(clean)
                    except Exception as e:
                        log(f"JSON decode error: {e}")
                        return None

            if time.time() - last_reload > 10:
                try:
                    log("reload")
                    driver.get(url)
                except Exception:
                    pass
                last_reload = time.time()
            time.sleep(1.0)

        raise RuntimeError("totally failed")

    finally:
        try:
            driver.close()
        except Exception:
            pass
        if server is not None:
            stop_ser(server)


# COINCLIX pc
def pc_solver(ua=None):
    server = None
    driver = make_driver(ua=ua)
    try:
        server = start_ser(port=8000)
        url = "http://127.0.0.1:8000/PC.html"
        log(f"open local puzzle: {url}")
        driver.get(url)

        log("solve puzzle lalu klik Submit...")

        for _ in range(600):
            try:
                els = driver.find_elements(By.ID, "pc_done")
                if els:
                    txt = els[0].get_attribute("textContent")
                    if txt:
                        txt = txt.strip()
                        json.loads(txt)  # validasi JSON
                        log("result captured")
                        return txt
            except Exception as e:
                if driver_dead(e):
                    raise
            time.sleep(1)

        raise RuntimeError("timeout waiting puzzle result")

    finally:
        try:
            driver.close()
        except:
            pass
        if server:
            stop_ser(server)





PX = None  # global
def pop_px_arg(argv):
    """
    Ambil --px host:port dari argv, return tuple (host,port) atau None.
    Hapus juga tokennya dari argv supaya indexing method/url tetap sama.
    """
    if "--px" not in argv:
        return None
    i = argv.index("--px")
    if i + 1 >= len(argv):
        del argv[i:i+1]
        return None
    raw = argv[i + 1].strip()
    del argv[i:i+2]

    if ":" not in raw:
        return None
    h, p = raw.rsplit(":", 1)
    try:
        return (h.strip(), int(p.strip()))
    except:
        return None


# =========================
# MAIN
if __name__ == "__main__":

    PX = pop_px_arg(sys.argv)

    if len(sys.argv) < 2:
        log("python execPy.py <method> [url] [action]")
        sys.exit(1)

    METHOD = sys.argv[1].lower()


    # =================
    # BUILD
    if METHOD == "build":
        ua_in = sys.argv[2] if len(sys.argv) >= 3 else None
        fp = build(ua_in)
        print(json.dumps(fp) if isinstance(fp, (dict, list)) else (fp or ""))
        sys.exit(0)


    # =================
    # USER AGENT
    if METHOD == "ua":
        ua = sys.argv[2] if len(sys.argv) >= 3 else None
        print(check_ua(ua))
        sys.exit(0)


    # =================
    # PC PUZZLE
    if METHOD == "pc":
        ua = sys.argv[2] if len(sys.argv) >= 3 else None
        print(pc_solver(ua))
        sys.exit(0)


    # =================
    # METHOD YANG BUTUH URL
    if len(sys.argv) < 3:
        log("python execPy.py <method> <url> [action]")
        sys.exit(1)

    URL = sys.argv[2]


    # =================
    # TURNSTILE
    if METHOD == "turnstile":
        ua = sys.argv[3] if len(sys.argv) >= 4 else None
        cf = sys.argv[4] if len(sys.argv) >= 5 else None
        print(json.dumps(turnstile(URL, ua, cf)))
        sys.exit(0)


    # =================
    # INTERSTITIAL
    elif METHOD == "inter":
        ua = sys.argv[3] if len(sys.argv) >= 4 else None
        print(json.dumps(interstitial(URL, ua)))
        sys.exit(0)


    # =================
    # RECAPTCHA V3
    elif METHOD == "recaptcha3":
        if len(sys.argv) < 4:
            log("need action method")
            sys.exit(1)

        ACTION = sys.argv[3]
        ua = sys.argv[4] if len(sys.argv) >= 5 else None
        cf = sys.argv[5] if len(sys.argv) >= 6 else None

        print(json.dumps(recaptcha3(URL, ACTION, ua, cf)))
        sys.exit(0)


    # =================
    # INVALID
    else:
        log("invalid method")
        sys.exit(1)