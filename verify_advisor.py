import os
from playwright.sync_api import sync_playwright

def run_cuj(page):
    page.goto("http://127.0.0.1:8000/frontend/index.html")
    page.wait_for_timeout(1000)

    # 1. Login
    page.fill("#login-username", "admin")
    page.fill("#login-password", "admin123")
    page.click("#login-step-1 button[type='submit']")
    page.wait_for_timeout(1000)

    # 2. 2FA Code
    page.fill('[id="2fa-code"]', "123456")
    page.click("#login-step-2 button[type='submit']")
    page.wait_for_timeout(1000)

    # 3. Select Company & Year
    page.select_option("#select-empresa", index=1)
    page.wait_for_timeout(500)
    page.select_option("#select-anio", value="2025")
    page.wait_for_timeout(500)
    page.click("#company-select-screen button[type='submit']")
    page.wait_for_timeout(1500)

    # 4. Open Clientes Page from Dynamic Menu Tree
    page.get_by_text("Mantenimiento de Clientes").click()
    page.wait_for_timeout(1000)

    # Take screenshot of Dashboard UI with Granate theme
    page.screenshot(path="/home/jules/verification/screenshots/advisor_dashboard.png")
    page.wait_for_timeout(1000)

if __name__ == "__main__":
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        context = browser.new_context(
            viewport={"width": 1280, "height": 800},
            record_video_dir="/home/jules/verification/videos"
        )
        page = context.new_page()
        try:
            run_cuj(page)
        finally:
            context.close()
            browser.close()
