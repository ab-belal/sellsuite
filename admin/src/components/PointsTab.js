"use client"

import { useState, useEffect } from "@wordpress/element"
import { Card, CardBody, CardHeader, TextControl, ToggleControl, Spinner, Notice } from "@wordpress/components"
import apiFetch from "@wordpress/api-fetch"
import SettingsSaveButton from "./SettingsSaveButton"

const PointsTab = () => {
  const [settings, setSettings] = useState({
    points_enabled: true,
    points_per_dollar: 1,
    points_redemption_rate: 100,
    points_expiry_days: 365,
  })
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [notice, setNotice] = useState(null)

  useEffect(() => {
    loadSettings()
  }, [])

  const loadSettings = async () => {
    try {
      const data = await apiFetch({ path: "/sellsuite/v1/settings" })
      setSettings(data)
    } catch (error) {
      setNotice({ type: "error", message: "Failed to load settings" })
    } finally {
      setLoading(false)
    }
  }

  const handleSave = async () => {
    setSaving(true)
    setNotice(null)

    try {
      await apiFetch({
        path: "/sellsuite/v1/settings",
        method: "POST",
        data: settings,
      })
      setNotice({ type: "success", message: "Settings saved successfully!" })
    } catch (error) {
      setNotice({ type: "error", message: "Failed to save settings" })
    } finally {
      setSaving(false)
    }
  }

  const updateSetting = (key, value) => {
    setSettings({ ...settings, [key]: value })
  }

  if (loading) {
    return (
      <div className="sellsuite-loading">
        <Spinner />
      </div>
    )
  }

  return (
    <div className="sellsuite-points-tab">
      {notice && (
        <Notice status={notice.type} isDismissible onRemove={() => setNotice(null)}>
          {notice.message}
        </Notice>
      )}

      <Card>
        <CardHeader>
          <h2>Points System Configuration</h2>
        </CardHeader>
        <CardBody>
          <ToggleControl
            label="Enable Points System"
            help="Allow customers to earn and redeem loyalty points"
            checked={settings.points_enabled}
            onChange={(value) => updateSetting("points_enabled", value)}
          />

          <TextControl
            label="Points Per Dollar"
            help="How many points customers earn per dollar spent"
            type="number"
            value={settings.points_per_dollar}
            onChange={(value) => updateSetting("points_per_dollar", Number.parseFloat(value) || 0)}
            min="0"
            step="0.1"
          />

          <TextControl
            label="Points Redemption Rate"
            help="How many points equal $1 in redemption value"
            type="number"
            value={settings.points_redemption_rate}
            onChange={(value) => updateSetting("points_redemption_rate", Number.parseInt(value) || 0)}
            min="1"
          />

          <TextControl
            label="Points Expiry (Days)"
            help="Number of days before points expire (0 for no expiry)"
            type="number"
            value={settings.points_expiry_days}
            onChange={(value) => updateSetting("points_expiry_days", Number.parseInt(value) || 0)}
            min="0"
          />
        </CardBody>
      </Card>

      <Card className="sellsuite-points-preview">
        <CardHeader>
          <h3>Points Calculation Preview</h3>
        </CardHeader>
        <CardBody>
          <p>
            <strong>Example:</strong> A customer spending $100 will earn{" "}
            <strong>{(100 * settings.points_per_dollar).toFixed(0)} points</strong>
          </p>
          <p>
            <strong>{settings.points_redemption_rate} points</strong> can be redeemed for <strong>$1.00</strong>{" "}
            discount
          </p>
        </CardBody>
      </Card>

      <SettingsSaveButton onClick={handleSave} isSaving={saving} />
    </div>
  )
}

export default PointsTab
