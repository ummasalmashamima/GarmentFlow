import { Link } from 'react-router-dom'
import { masterDataModules } from '../../constants/masterDataModules'

const moduleIcons = {
  buyers: '🛍️',
  customers: '👥',
  suppliers: '🏭',
  categories: '📁',
  products: '👕',
  'product-variants': '🏷️',
  sizes: '📏',
  colors: '🎨',
  units: '⚖️',
  materials: '🧵',
  warehouses: '🏢',
  'warehouse-locations': '📍',
}

function MasterDataIndex() {
  return (
    <div className="master-data-page">
      <div className="page-intro master-data-intro">
        <div>
          <p className="eyebrow">Enterprise Core Registers</p>
          <h1>Master Data Management</h1>
          <p>
            Centralized register for commercial buyers, suppliers, products, sizes, colors,
            raw materials, and multi-warehouse physical bin locations.
          </p>
        </div>
      </div>

      <div className="module-grid" aria-label="Master Data modules">
        {masterDataModules.map((module, index) => (
          <Link className="module-card" key={module.resource} to={`/master-data/${module.resource}`}>
            <div className="card-topline">
              <span className="module-icon" style={{ fontSize: '24px' }}>
                {moduleIcons[module.resource] || '📄'}
              </span>
              <span className="card-index">0{index + 1}</span>
            </div>
            <h2>{module.label}</h2>
            <p>{module.description}</p>
            <span className="card-link">Open {module.singular} Register</span>
          </Link>
        ))}
      </div>

      <div className="foundation-banner" style={{ marginTop: '32px', background: '#ffffff', padding: '24px', borderRadius: '16px', border: '1px solid var(--border)', display: 'flex', alignItems: 'center', gap: '16px' }}>
        <div style={{ width: '40px', height: '40px', borderRadius: '10px', background: 'var(--primary-light)', color: 'var(--primary)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 800 }}>
          ✓
        </div>
        <div>
          <p className="eyebrow">Enterprise Data Integrity</p>
          <h3 style={{ fontSize: '16px', fontWeight: 700, margin: '2px 0 4px', color: 'var(--slate-900)' }}>Normalized Single Source of Truth</h3>
          <p style={{ fontSize: '13px', color: 'var(--slate-500)', margin: 0 }}>
            Every record enforces foreign key constraints, field validations, status checks, and audit logging across all supply chain transactions.
          </p>
        </div>
      </div>
    </div>
  )
}

export default MasterDataIndex
