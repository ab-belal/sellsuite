"use client"

import { useState, useEffect } from "@wordpress/element"
import { Panel, PanelBody, PanelRow, TextControl, ToggleControl, Button, Notice, Spinner } from "@wordpress/components"
import apiFetch from "@wordpress/api-fetch"

const SettingsPage = () => {
  const [settings, setSettings] = useState({
    points_enabled: true,
    points_per_dollar: 1,
    points_redemption_rate: 100,
    min_redemption_points: 100,
  })
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [notice, setNotice] = useState(null)

  useEffect(() => {
    loadSettings()
  }, [])

  const loadSettings = async () => {
    try {
      const response = await apiFetch({
        path: "/sellsuite/v1/settings",
        method: "GET",
      })
      setSettings(response)
      setLoading(false)
    } catch (error) {
      console.error("Error loading settings:", error)
      setNotice({ type: "error", message: "Failed to load settings" })
      setLoading(false)
    }
  }

  const saveSettings = async () => {
    setSaving(true)
    setNotice(null)

    try {
      await apiFetch({
        path: "/sellsuite/v1/settings",
        method: "POST",
        data: settings,
      })
      setNotice({ type: "success", message: "Settings saved successfully!" })
      setSaving(false)
    } catch (error) {
      console.error("Error saving settings:", error)
      setNotice({ type: "error", message: "Failed to save settings" })
      setSaving(false)
    }
  }

  if (loading) {
    return (
      <div style={{ padding: "20px", textAlign: "center" }}>
        <Spinner />
      </div>
    )
  }

  return (
    <div className="sellsuite-settings-page">
      {notice && (
        <Notice status={notice.type} isDismissible onRemove={() => setNotice(null)}>
          {notice.message}
        </Notice>
      )}

      <Panel>
        <PanelBody title="Points System Settings" initialOpen={true}>
          <PanelRow>
            <ToggleControl
              label="Enable Points System"
              help="Turn on/off the loyalty points system"
              checked={settings.points_enabled}
              onChange={(value) => setSettings({ ...settings, points_enabled: value })}
            />
          </PanelRow>

          <PanelRow>
            <TextControl
              label="Points Per Dollar"
              help="How many points customers earn per dollar spent"
              type="number"
              value={settings.points_per_dollar}
              onChange={(value) => setSettings({ ...settings, points_per_dollar: Number.parseFloat(value) || 0 })}
            />
          </PanelRow>

          <PanelRow>
            <TextControl
              label="Points Redemption Rate"
              help="How many points equal $1 in redemption value"
              type="number"
              value={settings.points_redemption_rate}
              onChange={(value) =>
                setSettings({
                  ...settings,
                  points_redemption_rate: Number.parseFloat(value) || 0,
                })
              }
            />
          </PanelRow>

          <PanelRow>
            <TextControl
              label="Minimum Redemption Points"
              help="Minimum points required to redeem"
              type="number"
              value={settings.min_redemption_points}
              onChange={(value) =>
                setSettings({
                  ...settings,
                  min_redemption_points: Number.parseInt(value) || 0,
                })
              }
            />
          </PanelRow>
        </PanelBody>

        <PanelBody title="Email Notifications" initialOpen={false}>
          <PanelRow>
            <ToggleControl
              label="Send Points Earned Email"
              help="Send email notification when customers earn points"
              checked={settings.email_points_earned || false}
              onChange={(value) => setSettings({ ...settings, email_points_earned: value })}
            />
          </PanelRow>

          <PanelRow>
            <ToggleControl
              label="Send Points Expiry Reminder"
              help="Send reminder before points expire"
              checked={settings.email_points_expiry || false}
              onChange={(value) => setSettings({ ...settings, email_points_expiry: value })}
            />
          </PanelRow>
        </PanelBody>

        <PanelBody title="Display Settings" initialOpen={false}>
          <PanelRow>
            <ToggleControl
              label="Show Points on Product Pages"
              help="Display potential points on product pages"
              checked={settings.show_points_product || true}
              onChange={(value) => setSettings({ ...settings, show_points_product: value })}
            />
          </PanelRow>

          <PanelRow>
            <ToggleControl
              label="Show Points in Cart"
              help="Display points information in cart"
              checked={settings.show_points_cart || true}
              onChange={(value) => setSettings({ ...settings, show_points_cart: value })}
            />
          </PanelRow>

          <PanelRow>
            <ToggleControl
              label="Show Points on My Account"
              help="Display points summary on customer account page"
              checked={settings.show_points_account || true}
              onChange={(value) => setSettings({ ...settings, show_points_account: value })}
            />
          </PanelRow>
        </PanelBody>
      </Panel>

      <div style={{ marginTop: "20px" }}>
        <Button variant="primary" onClick={saveSettings} isBusy={saving}>
          {saving ? "Saving..." : "Save Settings"}
        </Button>
      </div>
    </div>
  )
}

export default SettingsPage
