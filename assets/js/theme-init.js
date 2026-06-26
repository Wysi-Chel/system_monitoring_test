(function () {
    try {
        var savedTheme = window.localStorage ? window.localStorage.getItem("systemMonitoringTheme") : null;

        if (savedTheme === "dark") {
            document.documentElement.classList.add("dark-theme");
        }
    } catch (error) {
    }
}());
