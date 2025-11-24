"use client"

import { useState, useEffect } from "@wordpress/element"
import { Card, CardBody, CardHeader, SearchControl, Button, Spinner, Notice } from "@wordpress/components"

const CustomersTab = () => {
  const [searchTerm, setSearchTerm] = useState("")
  const [customers, setCustomers] = useState([])
  const [loading, setLoading] = useState(false)
  const [notice, setNotice] = useState(null)

  const sampleCustomers = [
    {
      id: 1,
      name: "John Doe",
      email: "john@example.com",
      points: 1250,
      orders_count: 15,
      total_spent: 1250.0,
    },
    {
      id: 2,
      name: "Jane Smith",
      email: "jane@example.com",
      points: 850,
      orders_count: 8,
      total_spent: 850.0,
    },
    {
      id: 3,
      name: "Bob Johnson",
      email: "bob@example.com",
      points: 2100,
      orders_count: 21,
      total_spent: 2100.0,
    },
  ]

  useEffect(() => {
    // Load sample data for demonstration
    setCustomers(sampleCustomers)
  }, [])

  const handleSearch = (value) => {
    setSearchTerm(value)
    // Filter customers based on search term
    if (value) {
      const filtered = sampleCustomers.filter(
        (customer) =>
          customer.name.toLowerCase().includes(value.toLowerCase()) ||
          customer.email.toLowerCase().includes(value.toLowerCase()),
      )
      setCustomers(filtered)
    } else {
      setCustomers(sampleCustomers)
    }
  }

  return (
    <div className="sellsuite-customers-tab">
      {notice && (
        <Notice status={notice.type} isDismissible onRemove={() => setNotice(null)}>
          {notice.message}
        </Notice>
      )}

      <Card>
        <CardHeader>
          <h2>Customer Management</h2>
        </CardHeader>
        <CardBody>
          <SearchControl
            label="Search Customers"
            value={searchTerm}
            onChange={handleSearch}
            placeholder="Search by name or email..."
          />
        </CardBody>
      </Card>

      {loading ? (
        <div className="sellsuite-loading">
          <Spinner />
        </div>
      ) : (
        <Card>
          <CardBody>
            <table className="sellsuite-customers-table">
              <thead>
                <tr>
                  <th>Customer</th>
                  <th>Email</th>
                  <th>Points</th>
                  <th>Orders</th>
                  <th>Total Spent</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {customers.length > 0 ? (
                  customers.map((customer) => (
                    <tr key={customer.id}>
                      <td>{customer.name}</td>
                      <td>{customer.email}</td>
                      <td>
                        <strong>{customer.points}</strong>
                      </td>
                      <td>{customer.orders_count}</td>
                      <td>${customer.total_spent.toFixed(2)}</td>
                      <td>
                        <Button variant="secondary" size="small">
                          View Details
                        </Button>
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan="6" style={{ textAlign: "center" }}>
                      No customers found
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </CardBody>
        </Card>
      )}

      <Card>
        <CardHeader>
          <h3>Customer Insights</h3>
        </CardHeader>
        <CardBody>
          <div className="sellsuite-stats-grid">
            <div className="sellsuite-stat">
              <span className="sellsuite-stat-label">Total Customers</span>
              <span className="sellsuite-stat-value">{customers.length}</span>
            </div>
            <div className="sellsuite-stat">
              <span className="sellsuite-stat-label">Total Points Issued</span>
              <span className="sellsuite-stat-value">{customers.reduce((sum, c) => sum + c.points, 0)}</span>
            </div>
            <div className="sellsuite-stat">
              <span className="sellsuite-stat-label">Avg Points per Customer</span>
              <span className="sellsuite-stat-value">
                {customers.length > 0
                  ? Math.round(customers.reduce((sum, c) => sum + c.points, 0) / customers.length)
                  : 0}
              </span>
            </div>
          </div>
        </CardBody>
      </Card>
    </div>
  )
}

export default CustomersTab
