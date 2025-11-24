"use client"

import { useState } from "@wordpress/element"
import { Card, CardBody, CardHeader, Button, Notice } from "@wordpress/components"

const ProductsTab = () => {
  const [notice, setNotice] = useState(null)

  return (
    <div className="sellsuite-products-tab">
      {notice && (
        <Notice status={notice.type} isDismissible onRemove={() => setNotice(null)}>
          {notice.message}
        </Notice>
      )}

      <Card>
        <CardHeader>
          <h2>Product Management</h2>
        </CardHeader>
        <CardBody>
          <p>Enhanced product management features will be available here. This section can include:</p>
          <ul>
            <li>Bulk product editing</li>
            <li>Product points multipliers</li>
            <li>Special promotions and bonus points</li>
            <li>Product-specific loyalty rewards</li>
          </ul>
          <Button variant="secondary" className="sellsuite-coming-soon">
            Coming Soon
          </Button>
        </CardBody>
      </Card>

      <Card>
        <CardHeader>
          <h3>Points Multipliers</h3>
        </CardHeader>
        <CardBody>
          <p>
            Set custom point multipliers for specific products or categories to encourage purchases and reward customer
            loyalty.
          </p>
        </CardBody>
      </Card>
    </div>
  )
}

export default ProductsTab
