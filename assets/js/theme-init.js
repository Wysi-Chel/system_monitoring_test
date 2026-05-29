(function () {
    try {
        var savedTheme = window.localStorage ? window.localStorage.getItem("systemMonitoringTheme") : null;

        if (savedTheme !== "light") {
            document.documentElement.classList.add("dark-theme");
        }
    } catch (error) {
        document.documentElement.classList.add("dark-theme");
    }
}());
