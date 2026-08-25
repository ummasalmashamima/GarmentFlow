import { Link } from 'react-router-dom'
import { masterDataModules } from '../../constants/masterDataModules'

function MasterDataIndex() {
  return (
    <div className="master-data-page">
      <div className="page-intro master-data-intro">
        <div>
          <p className="eyebrow">Phase 2 · Reference architecture</p>
          <h1>Master Data</h1>
          <p>One consistent register for the entities that keep garment operations connected.</p>
        </div>
      </div>

      <section className="module-grid" aria-label="Master Data modules">
        {masterDataModules.map((module, index) => (
          <Link className="module-card" key={module.resource} to={`/master-data/${module.resource}`}>
            <span className="module-number">{String(index + 1).padStart(2, '0')}</span>
            <h2>{module.label}</h2>
            <p>{module.description}</p>
            <span className="module-link">Open register ↗</span>
          </Link>
        ))}
      </section>

      <div className="phase-banner">
        <span className="phase-banner-mark" aria-hidden="true">M</span>
        <div>
          <p className="eyebrow">Structured for the next workflow</p>
          <h2>Reference data before transactions.</h2>
          <p>All Phase 2 records use shared validation, permissions, audit logging, and reusable list/detail flows.</p>
        </div>
      </div>
    </div>
  )
}

export default MasterDataIndex
