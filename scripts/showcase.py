#!/usr/bin/env python3
"""
EduPulse showcase helper (needs `pip install playwright`).

  python3 scripts/showcase.py --check-layout   # every page, desktop + phone: no sideways scroll, no console errors
  python3 scripts/showcase.py --screenshots    # docs/screenshots/*.png
  python3 scripts/showcase.py --walkthrough    # docs/walkthrough.mp4 and docs/walkthrough.gif (needs ffmpeg)

Set EDUPULSE_URL (default http://localhost:8080) and, to reuse an existing
Chromium build, PLAYWRIGHT_CHROMIUM_EXECUTABLE.
"""
import argparse
import os
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path

from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parent.parent
BASE = os.environ.get("EDUPULSE_URL", "http://localhost:8080").rstrip("/")
DESKTOP = {"width": 1280, "height": 800}
PHONE = {"width": 390, "height": 844}
STUDENT = ("student@edupulse.test", "Student123!")
INSTRUCTOR = ("instructor@edupulse.test", "Instructor123!")


def reseed():
    subprocess.run(["docker", "compose", "exec", "-T", "app", "php", "database/seed.php"], cwd=ROOT, check=True,
                   stdout=subprocess.DEVNULL)


def launch(p):
    exe = os.environ.get("PLAYWRIGHT_CHROMIUM_EXECUTABLE")
    return p.chromium.launch(executable_path=exe) if exe else p.chromium.launch()


def login(page, account):
    page.goto(f"{BASE}/login.php")
    page.fill("#email", account[0])
    page.fill("#password", account[1])
    page.click("#loginForm button[type=submit]")
    page.wait_for_load_state("networkidle")


def first_id(page, path, selector):
    """Open a page and return the id from the first link matching selector."""
    page.goto(f"{BASE}/{path}")
    href = page.get_attribute(selector, "href")
    return href.split("id=")[-1] if href else "1"


def pages_for(page, role):
    if role == "guest":
        return ["index.php", "login.php", "register.php"]
    if role == "student":
        course = first_id(page, "student_dashboard.php", "a[href*='course_detail.php']")
        assignment = first_id(page, f"course_detail.php?id={course}", "a[href*='assignment.php']")
        return ["index.php", "student_dashboard.php", "courses.php", "courses.php?q=zzz", f"course_detail.php?id={course}",
                f"assignment.php?id={assignment}", "profile.php"]
    course = first_id(page, "instructor_dashboard.php", "a[href*='course_detail.php']")
    assignment = first_id(page, f"course_detail.php?id={course}", "a[href*='assignment.php']")
    return ["instructor_dashboard.php", "courses.php", f"course_detail.php?id={course}", f"assignment.php?id={assignment}",
            "profile.php"]


def check_layout():
    reseed()
    problems = []
    with sync_playwright() as p:
        browser = launch(p)
        for role, account in (("guest", None), ("student", STUDENT), ("instructor", INSTRUCTOR)):
            for name, viewport in (("desktop", DESKTOP), ("phone", PHONE)):
                context = browser.new_context(viewport=viewport)
                page = context.new_page()
                errors = []
                page.on("console", lambda m, errors=errors: errors.append(m.text) if m.type == "error" else None)
                page.on("pageerror", lambda e, errors=errors: errors.append(str(e)))
                if account:
                    login(page, account)
                for path in pages_for(page, role):
                    errors.clear()
                    response = page.goto(f"{BASE}/{path}")
                    page.wait_for_load_state("networkidle")
                    status = response.status if response else 0
                    overflow = page.evaluate("document.documentElement.scrollWidth - window.innerWidth")
                    label = f"{role:<10} {name:<7} {path}"
                    if status != 200:
                        problems.append(f"{label}: HTTP {status}")
                    if overflow > 0:
                        problems.append(f"{label}: scrolls sideways by {overflow}px")
                    for err in errors:
                        problems.append(f"{label}: console error: {err}")
                context.close()
        browser.close()
    if problems:
        print("\n".join(problems))
        sys.exit(1)
    print("layout OK")


SHOTS = [
    # (file, account, path or callable, viewport)
    ("01-landing.png", None, "index.php", DESKTOP),
    ("02-student-dashboard.png", STUDENT, "student_dashboard.php", DESKTOP),
    ("03-course-page.png", STUDENT, "course", DESKTOP),
    ("04-assignment-graded.png", STUDENT, "graded", DESKTOP),
    ("05-instructor-dashboard.png", INSTRUCTOR, "instructor_dashboard.php", DESKTOP),
    ("06-grading.png", INSTRUCTOR, "grading", DESKTOP),
    ("07-mobile-dashboard.png", STUDENT, "student_dashboard.php", PHONE),
]


def resolve(page, target):
    if target == "course":
        card = ".course-card:has-text('Introduction to Programming') a[href*=course_detail]"
        return f"course_detail.php?id={first_id(page, 'student_dashboard.php', card)}"
    if target == "graded":
        return f"assignment.php?id={_assignment_id('ER Diagram for a Library')}"
    if target == "grading":
        return f"assignment.php?id={_assignment_id('Functions Lab')}"
    return target


def _assignment_id(title):
    out = subprocess.run(
        ["docker", "compose", "exec", "-T", "db", "sh", "-c",
         'mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE" -N -e "SELECT id FROM assignments WHERE title = \'%s\'"' % title],
        cwd=ROOT, check=True, capture_output=True, text=True)
    return out.stdout.strip()


def screenshots():
    reseed()
    out = ROOT / "docs" / "screenshots"
    out.mkdir(parents=True, exist_ok=True)
    with sync_playwright() as p:
        browser = launch(p)
        for filename, account, target, viewport in SHOTS:
            context = browser.new_context(viewport=viewport, device_scale_factor=2)
            page = context.new_page()
            if account:
                login(page, account)
            page.goto(f"{BASE}/{resolve(page, target)}")
            page.wait_for_load_state("networkidle")
            page.add_style_tag(content=".alert-flash{display:none!important} *{transition:none!important}")
            if filename == "06-grading.png":
                page.click("button[data-bs-target^='#gradeModal']")
                page.wait_for_selector(".modal.show")
                page.locator(".modal.show input[name=grade]").fill("18")
                page.locator(".modal.show textarea[name=feedback]").fill("Well-named functions. Add a docstring to parse_marks().")
                page.wait_for_timeout(300)
            page.screenshot(path=str(out / filename), full_page=viewport is not PHONE)
            print("saved", out / filename)
            context.close()
        browser.close()


def walkthrough():
    if not shutil.which("ffmpeg"):
        sys.exit("ffmpeg is required for --walkthrough")
    reseed()
    frames = Path(tempfile.mkdtemp(prefix="edupulse-frames-"))
    count = 0

    def snap(page, hold=1):
        nonlocal count
        page.wait_for_timeout(250)
        for _ in range(hold):
            page.screenshot(path=str(frames / f"{count:04d}.png"))
            count += 1

    view = {"width": 1280, "height": 720}
    with sync_playwright() as p:
        browser = launch(p)
        # Instructor creates an assignment
        ctx = browser.new_context(viewport=view)
        page = ctx.new_page()
        page.goto(f"{BASE}/login.php"); snap(page, 2)
        page.fill("#email", INSTRUCTOR[0]); page.fill("#password", INSTRUCTOR[1]); snap(page)
        page.click("#loginForm button[type=submit]"); page.wait_for_load_state("networkidle"); snap(page, 3)
        page.click(".course-card:has-text('Introduction to Programming') a:has-text('Manage')"); page.wait_for_load_state("networkidle"); snap(page, 2)
        page.click("button[data-bs-target='#addAssignmentModal']"); page.wait_for_selector("#addAssignmentModal.show"); snap(page)
        modal = page.locator("#addAssignmentModal")
        modal.locator("input[name=title]").fill("Recursion Practice"); snap(page)
        modal.locator("textarea[name=description]").fill("Solve the three recursion exercises and explain the base case of each."); snap(page)
        modal.locator("input[name=due_date]").fill("2030-06-01T17:00")
        modal.locator("input[name=max_points]").fill("20"); snap(page, 2)
        modal.locator("button[type=submit]").click(); page.wait_for_load_state("networkidle"); snap(page, 3)
        ctx.close()
        # Student submits
        ctx = browser.new_context(viewport=view)
        page = ctx.new_page()
        login(page, STUDENT); snap(page, 3)
        page.click(".course-card:has-text('Introduction to Programming') a:has-text('Open Course')"); page.wait_for_load_state("networkidle"); snap(page, 2)
        page.click("text=Recursion Practice"); page.wait_for_load_state("networkidle"); snap(page, 2)
        page.set_input_files("input[name=submission_file]", str(ROOT / "database" / "seed-files" / "student-submission.pdf")); snap(page)
        page.click("form:has(input[name=submission_file]) button[type=submit]"); page.wait_for_load_state("networkidle"); snap(page, 3)
        ctx.close()
        # Instructor grades
        ctx = browser.new_context(viewport=view)
        page = ctx.new_page()
        login(page, INSTRUCTOR)
        page.click(".course-card:has-text('Introduction to Programming') a:has-text('Manage')"); page.wait_for_load_state("networkidle")
        page.click("text=Recursion Practice"); page.wait_for_load_state("networkidle"); snap(page, 2)
        page.click("button[data-bs-target^='#gradeModal']"); page.wait_for_selector(".modal.show"); snap(page)
        page.locator(".modal.show input[name=grade]").fill("18")
        page.locator(".modal.show textarea[name=feedback]").fill("Clear base cases. Add a test for n = 0."); snap(page, 2)
        page.locator(".modal.show button[type=submit]").click(); page.wait_for_load_state("networkidle"); snap(page, 3)
        ctx.close()
        # Student sees the grade
        ctx = browser.new_context(viewport=view)
        page = ctx.new_page()
        login(page, STUDENT)
        page.click(".course-card:has-text('Introduction to Programming') a:has-text('Open Course')"); page.wait_for_load_state("networkidle")
        page.click("text=Recursion Practice"); page.wait_for_load_state("networkidle"); snap(page, 5)
        ctx.close()
        browser.close()

    docs = ROOT / "docs"
    pattern = str(frames / "%04d.png")
    subprocess.run(["ffmpeg", "-y", "-loglevel", "error", "-framerate", "1.25", "-i", pattern,
                    "-vf", "fps=12,format=yuv420p", "-c:v", "libx264", "-movflags", "+faststart",
                    str(docs / "walkthrough.mp4")], check=True)
    palette = frames / "palette.png"
    subprocess.run(["ffmpeg", "-y", "-loglevel", "error", "-framerate", "1.25", "-i", pattern,
                    "-vf", "scale=960:-1:flags=lanczos,palettegen=max_colors=128", str(palette)], check=True)
    subprocess.run(["ffmpeg", "-y", "-loglevel", "error", "-framerate", "1.25", "-i", pattern, "-i", str(palette),
                    "-lavfi", "scale=960:-1:flags=lanczos[x];[x][1:v]paletteuse=dither=bayer:bayer_scale=5",
                    str(docs / "walkthrough.gif")], check=True)
    shutil.rmtree(frames)
    reseed()
    print("saved", docs / "walkthrough.mp4", "and", docs / "walkthrough.gif")


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--check-layout", action="store_true")
    parser.add_argument("--screenshots", action="store_true")
    parser.add_argument("--walkthrough", action="store_true")
    args = parser.parse_args()
    if not (args.check_layout or args.screenshots or args.walkthrough):
        parser.print_help()
        sys.exit(2)
    if args.check_layout:
        check_layout()
    if args.screenshots:
        screenshots()
    if args.walkthrough:
        walkthrough()
