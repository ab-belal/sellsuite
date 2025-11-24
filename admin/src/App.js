import { TabPanel } from "@wordpress/components"
import PointsTab from "./components/PointsTab"
import ProductsTab from "./components/ProductsTab"
import CustomersTab from "./components/CustomersTab"
import SettingsPage from "./components/SettingsPage"

const App = () => {
    const currentPage = window.sellsuiteData?.currentPage || "dashboard"

    if (currentPage === "sellsuite-settings") {
        return (
            <div className="sellsuite-admin-container">
                <SettingsPage />
            </div>
        )
    }

  const tabs = [
    {
      name: "points",
      title: "Points System",
      className: "sellsuite-tab-points",
    },
    {
      name: "products",
      title: "Product Management",
      className: "sellsuite-tab-products",
    },
    {
      name: "customers",
      title: "Customer Management",
      className: "sellsuite-tab-customers",
    },
  ]

    return (
        <div className="sellsuite-admin-container">
            <TabPanel className="sellsuite-tab-panel" activeClass="is-active" tabs={tabs}>
                {(tab) => {
                    switch (tab.name) {
                        case "points":
                        return <PointsTab />
                        case "products":
                        return <ProductsTab />
                        case "customers":
                        return <CustomersTab />
                        default:
                        return null
                    }
                }}
            </TabPanel>
        </div>
    )
}

export default App
