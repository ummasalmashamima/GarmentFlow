const statusField = {
  name: 'status',
  label: 'Status',
  type: 'select',
  options: [
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
  ],
  required: true,
}

const partyFields = [
  { name: 'code', label: 'Code', type: 'text', required: true },
  { name: 'name', label: 'Name', type: 'text', required: true },
  { name: 'contact_name', label: 'Contact name', type: 'text' },
  { name: 'email', label: 'Email', type: 'email' },
  { name: 'phone', label: 'Phone', type: 'text' },
  { name: 'country', label: 'Country', type: 'text' },
  { name: 'address', label: 'Address', type: 'textarea' },
  statusField,
  { name: 'notes', label: 'Notes', type: 'textarea' },
]

const buyerFields = [
  { name: 'code', label: 'Code', type: 'text', required: true },
  { name: 'name', label: 'Buyer Name', type: 'text', required: true },
  { name: 'company', label: 'Company', type: 'text' },
  { name: 'contact_name', label: 'Contact Person', type: 'text' },
  { name: 'email', label: 'Email', type: 'email' },
  { name: 'phone', label: 'Phone', type: 'text' },
  { name: 'country', label: 'Country', type: 'text' },
  { name: 'address', label: 'Address', type: 'textarea' },
  statusField,
  { name: 'notes', label: 'Notes', type: 'textarea' },
]

const baseColumns = [
  { key: 'code', label: 'Code' },
  { key: 'name', label: 'Name' },
  { key: 'status', label: 'Status' },
  { key: 'created_at', label: 'Created' },
]


const masterDataModules = [
  {
    resource: 'buyers', label: 'Buyers', singular: 'Buyer', description: 'Commercial buying organizations and account contacts.',
    columns: baseColumns, fields: buyerFields,
  },
  {
    resource: 'customers', label: 'Customers', singular: 'Customer', description: 'Customer records used across the connected operating picture.',
    columns: baseColumns, fields: partyFields,
  },
  {
    resource: 'suppliers', label: 'Suppliers', singular: 'Supplier', description: 'Supplier organizations and sourcing contacts.',
    columns: baseColumns, fields: partyFields,
  },
  {
    resource: 'categories', label: 'Categories', singular: 'Category', description: 'Hierarchical categories for finished products.',
    columns: [
      { key: 'code', label: 'Code' }, { key: 'name', label: 'Name' }, { key: 'parent', label: 'Parent', render: (record) => record.parent?.name || 'Root' }, { key: 'status', label: 'Status' },
    ],
    fields: [
      { name: 'parent_id', label: 'Parent category', type: 'relation', relation: 'categories' },
      { name: 'code', label: 'Code', type: 'text', required: true }, { name: 'name', label: 'Name', type: 'text', required: true },
      { name: 'description', label: 'Description', type: 'textarea' }, statusField,
    ],
  },
  {
    resource: 'products', label: 'Products', singular: 'Product', description: 'Finished-goods definitions with category, unit, and price metadata.',
    columns: [
      { key: 'code', label: 'Code' }, { key: 'name', label: 'Name' }, { key: 'category', label: 'Category', render: (record) => record.category?.name || '—' }, { key: 'standard_price', label: 'Standard price' }, { key: 'status', label: 'Status' },
    ],
    fields: [
      { name: 'category_id', label: 'Category', type: 'relation', relation: 'categories', required: true }, { name: 'unit_id', label: 'Unit', type: 'relation', relation: 'units', required: true },
      { name: 'code', label: 'Code', type: 'text', required: true }, { name: 'name', label: 'Name', type: 'text', required: true },
      { name: 'product_type', label: 'Product type', type: 'text' }, { name: 'description', label: 'Description', type: 'textarea' },
      { name: 'standard_cost', label: 'Standard cost', type: 'number', required: true }, { name: 'standard_price', label: 'Standard price', type: 'number', required: true }, statusField,
    ],
  },
  {
    resource: 'product-variants', label: 'Product Variants', singular: 'Product Variant', description: 'SKU-level product combinations of product, size, and color.',
    columns: [
      { key: 'sku', label: 'SKU' }, { key: 'variant_name', label: 'Variant' }, { key: 'product', label: 'Product', render: (record) => record.product?.name || '—' }, { key: 'selling_price', label: 'Selling price' }, { key: 'status', label: 'Status' },
    ],
    fields: [
      { name: 'product_id', label: 'Product', type: 'relation', relation: 'products', required: true }, { name: 'size_id', label: 'Size', type: 'relation', relation: 'sizes' }, { name: 'color_id', label: 'Color', type: 'relation', relation: 'colors' },
      { name: 'sku', label: 'SKU', type: 'text', required: true }, { name: 'variant_name', label: 'Variant name', type: 'text' },
      { name: 'cost_price', label: 'Cost price', type: 'number' }, { name: 'selling_price', label: 'Selling price', type: 'number' }, statusField, { name: 'description', label: 'Description', type: 'textarea' },
    ],
  },
  {
    resource: 'sizes', label: 'Sizes', singular: 'Size', description: 'Reusable size dimensions for product variants.',
    columns: [{ key: 'code', label: 'Code' }, { key: 'name', label: 'Name' }, { key: 'sort_order', label: 'Order' }, { key: 'status', label: 'Status' }],
    fields: [{ name: 'code', label: 'Code', type: 'text', required: true }, { name: 'name', label: 'Name', type: 'text', required: true }, { name: 'sort_order', label: 'Sort order', type: 'number', required: true }, statusField, { name: 'description', label: 'Description', type: 'textarea' }],
  },
  {
    resource: 'colors', label: 'Colors', singular: 'Color', description: 'Reusable color definitions for product variants.',
    columns: [{ key: 'code', label: 'Code' }, { key: 'name', label: 'Name' }, { key: 'hex_code', label: 'Hex' }, { key: 'status', label: 'Status' }],
    fields: [{ name: 'code', label: 'Code', type: 'text', required: true }, { name: 'name', label: 'Name', type: 'text', required: true }, { name: 'hex_code', label: 'Hex color', type: 'text' }, statusField, { name: 'description', label: 'Description', type: 'textarea' }],
  },
  {
    resource: 'materials', label: 'Materials', singular: 'Material', description: 'Material inputs linked to categories, units, and suppliers.',
    columns: [
      { key: 'code', label: 'Code' }, { key: 'name', label: 'Name' }, { key: 'material_category', label: 'Category', render: (record) => record.material_category?.name || '—' }, { key: 'status', label: 'Status' },
    ],
    fields: [
      { name: 'material_category_id', label: 'Material category', type: 'relation', relation: 'material-categories', required: true }, { name: 'unit_id', label: 'Unit', type: 'relation', relation: 'units', required: true },
      { name: 'code', label: 'Code', type: 'text', required: true }, { name: 'name', label: 'Name', type: 'text', required: true }, { name: 'material_type', label: 'Material type', type: 'text' }, statusField, { name: 'description', label: 'Description', type: 'textarea' },
    ],
  },
  {
    resource: 'material-categories', label: 'Material Categories', singular: 'Material Category', description: 'Classification for fabric, trim, packaging, and other inputs.',
    columns: baseColumns,
    fields: [{ name: 'code', label: 'Code', type: 'text', required: true }, { name: 'name', label: 'Name', type: 'text', required: true }, { name: 'description', label: 'Description', type: 'textarea' }, statusField],
  },
  {
    resource: 'units', label: 'Units', singular: 'Unit', description: 'Standard units of measure used by products and materials.',
    columns: [{ key: 'code', label: 'Code' }, { key: 'name', label: 'Name' }, { key: 'symbol', label: 'Symbol' }, { key: 'status', label: 'Status' }],
    fields: [{ name: 'code', label: 'Code', type: 'text', required: true }, { name: 'name', label: 'Name', type: 'text', required: true }, { name: 'symbol', label: 'Symbol', type: 'text' }, { name: 'decimal_places', label: 'Decimal places', type: 'number', required: true }, statusField, { name: 'description', label: 'Description', type: 'textarea' }],
  },
  {
    resource: 'warehouses', label: 'Warehouses', singular: 'Warehouse', description: 'Physical facilities that hold stock and operational locations.',
    columns: baseColumns,
    fields: [{ name: 'code', label: 'Code', type: 'text', required: true }, { name: 'name', label: 'Name', type: 'text', required: true }, { name: 'contact_name', label: 'Contact name', type: 'text' }, { name: 'phone', label: 'Phone', type: 'text' }, { name: 'country', label: 'Country', type: 'text' }, { name: 'address', label: 'Address', type: 'textarea' }, statusField, { name: 'notes', label: 'Notes', type: 'textarea' }],
  },
  {
    resource: 'warehouse-locations', label: 'Warehouse Locations', singular: 'Warehouse Location', description: 'Addressable storage locations within each warehouse.',
    columns: [{ key: 'code', label: 'Code' }, { key: 'name', label: 'Name' }, { key: 'warehouse', label: 'Warehouse', render: (record) => record.warehouse?.name || '—' }, { key: 'location_type', label: 'Type' }, { key: 'status', label: 'Status' }],
    fields: [{ name: 'warehouse_id', label: 'Warehouse', type: 'relation', relation: 'warehouses', required: true }, { name: 'code', label: 'Code', type: 'text', required: true }, { name: 'name', label: 'Name', type: 'text', required: true }, { name: 'location_type', label: 'Location type', type: 'text', required: true }, statusField, { name: 'description', label: 'Description', type: 'textarea' }],
  },
]

function getMasterDataModule(resource) {
  return masterDataModules.find((module) => module.resource === resource)
}

export { getMasterDataModule, masterDataModules }
