/**
 * SellSuite Frontend JavaScript
 * Handles frontend interactions for the points system
 */

;(($) => {
  // Declare jQuery variable
  const jQuery = window.jQuery

  // Initialize when DOM is ready
  $(document).ready(() => {
    // Points display animations
    const pointsDisplay = $(".sellsuite-points-total strong")
    if (pointsDisplay.length) {
      animatePoints(pointsDisplay)
    }

    // Toggle points history
    $(".sellsuite-toggle-history").on("click", (e) => {
      e.preventDefault()
      $(".sellsuite-points-history").slideToggle(300)
    })

    // Refresh points on AJAX complete (for cart updates)
    $(document).on("updated_cart_totals", () => {
      refreshPointsDisplay()
    })
  })

  /**
   * Animate points counter
   */
  function animatePoints($element) {
    const finalValue = Number.parseInt($element.text())
    if (isNaN(finalValue)) return

    let currentValue = 0
    const increment = Math.ceil(finalValue / 50)
    const duration = 1000
    const stepTime = duration / (finalValue / increment)

    const timer = setInterval(() => {
      currentValue += increment
      if (currentValue >= finalValue) {
        currentValue = finalValue
        clearInterval(timer)
      }
      $element.text(currentValue)
    }, stepTime)
  }

  /**
   * Refresh points display via AJAX
   */
  function refreshPointsDisplay() {
    // This would make an AJAX call to get updated points
    // Implementation depends on your specific needs
    console.log("Refreshing points display...")
  }

  /**
   * Show points notification
   */
  function showPointsNotification(message, type) {
    const notification = $('<div class="sellsuite-notification"></div>')
      .addClass("sellsuite-notification-" + type)
      .text(message)
      .appendTo("body")
      .fadeIn(300)

    setTimeout(() => {
      notification.fadeOut(300, function () {
        $(this).remove()
      })
    }, 3000)
  }

  // Expose functions globally if needed
  window.SellSuite = {
    showPointsNotification: showPointsNotification,
    refreshPointsDisplay: refreshPointsDisplay,
  }
})(window.jQuery)
