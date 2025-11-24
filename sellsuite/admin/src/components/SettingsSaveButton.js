"use client"

import { Button } from "@wordpress/components"

const SettingsSaveButton = ({ onClick, isSaving }) => {
  return (
    <div className="sellsuite-save-button-container">
      <Button variant="primary" onClick={onClick} isBusy={isSaving} disabled={isSaving}>
        {isSaving ? "Saving..." : "Save Settings"}
      </Button>
    </div>
  )
}

export default SettingsSaveButton
