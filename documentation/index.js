(function () {
    const body = document.body;
    const menuToggle = document.querySelector(".menu-toggle");
    const sidebar = document.getElementById("docsSidebar");
    const searchInput = document.getElementById("docsSearch");
    const noResults = document.getElementById("noResults");
    const sections = Array.from(document.querySelectorAll("[data-section]"));
    const tocLinks = Array.from(document.querySelectorAll(".toc a"));

    function closeMenu() {
        body.classList.remove("menu-open");
        if (menuToggle) {
            menuToggle.setAttribute("aria-expanded", "false");
        }
    }

    function setActiveLink(id) {
        tocLinks.forEach((link) => {
            link.classList.toggle("active", link.getAttribute("href") === `#${id}`);
        });
    }

    function addCopyButtons() {
        document.querySelectorAll("pre").forEach((pre) => {
            const button = document.createElement("button");
            button.type = "button";
            button.className = "copy-btn";
            button.textContent = "Copy";
            button.addEventListener("click", async () => {
                const code = pre.querySelector("code");
                const text = code ? code.innerText : pre.innerText;

                try {
                    await navigator.clipboard.writeText(text);
                    button.textContent = "Copied";
                    setTimeout(() => {
                        button.textContent = "Copy";
                    }, 1400);
                } catch (error) {
                    const range = document.createRange();
                    range.selectNodeContents(code || pre);
                    const selection = window.getSelection();
                    selection.removeAllRanges();
                    selection.addRange(range);
                    button.textContent = "Selected";
                    setTimeout(() => {
                        button.textContent = "Copy";
                    }, 1400);
                }
            });
            pre.appendChild(button);
        });
    }

    function filterSections() {
        const query = searchInput.value.trim().toLowerCase();
        let visibleCount = 0;

        sections.forEach((section) => {
            const text = section.innerText.toLowerCase();
            const isVisible = !query || text.includes(query);
            section.classList.toggle("is-hidden", !isVisible);
            if (isVisible) {
                visibleCount += 1;
            }
        });

        tocLinks.forEach((link) => {
            const target = document.querySelector(link.getAttribute("href"));
            link.classList.toggle("is-hidden", target && target.classList.contains("is-hidden"));
        });

        noResults.hidden = visibleCount !== 0;
    }

    if (menuToggle) {
        menuToggle.addEventListener("click", () => {
            const isOpen = body.classList.toggle("menu-open");
            menuToggle.setAttribute("aria-expanded", String(isOpen));
        });
    }

    tocLinks.forEach((link) => {
        link.addEventListener("click", (event) => {
            const target = document.querySelector(link.getAttribute("href"));
            if (!target) {
                return;
            }

            event.preventDefault();
            target.scrollIntoView({ behavior: "smooth", block: "start" });
            history.replaceState(null, "", link.getAttribute("href"));
            setActiveLink(target.id);
            closeMenu();
        });
    });

    document.addEventListener("click", (event) => {
        if (!body.classList.contains("menu-open")) {
            return;
        }

        const clickInsideSidebar = sidebar && sidebar.contains(event.target);
        const clickToggle = menuToggle && menuToggle.contains(event.target);
        if (!clickInsideSidebar && !clickToggle) {
            closeMenu();
        }
    });

    if (searchInput) {
        searchInput.addEventListener("input", filterSections);
    }

    const observer = new IntersectionObserver((entries) => {
        const visible = entries
            .filter((entry) => entry.isIntersecting)
            .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];

        if (visible) {
            setActiveLink(visible.target.id);
        }
    }, {
        rootMargin: "-20% 0px -65% 0px",
        threshold: [0.1, 0.35, 0.6]
    });

    sections.forEach((section) => observer.observe(section));
    addCopyButtons();

    if (location.hash) {
        const initial = document.querySelector(location.hash);
        if (initial) {
            setActiveLink(initial.id);
        }
    } else if (sections[0]) {
        setActiveLink(sections[0].id);
    }
})();
