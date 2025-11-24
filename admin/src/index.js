import { render } from "@wordpress/element"
import App from "./App"
import "./styles.css"

// Wait for DOM to be ready
document.addEventListener("DOMContentLoaded", () => {
    const dashboardElement = document.getElementById("sellsuite-admin-app")
    const settingsElement = document.getElementById("sellsuite-settings-app")

    const rootElement = dashboardElement || settingsElement

    if (rootElement) {
        render(<App />, rootElement)
    }
})
