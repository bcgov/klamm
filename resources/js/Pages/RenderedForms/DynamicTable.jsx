import React, { useState, useEffect } from 'react';
import {
  DataTable,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableHeader,
  TableRow,
  TextInput,
  Button
} from 'carbon-components-react';


const DynamicTable = ({ tableTitle,initialRows, initialColumns, initialHeaderNames }) => {
    const [headers, setHeaders] = useState([]);
    const [rows, setRows] = useState([]);
  
    useEffect(() => {
      const headerArray = initialHeaderNames.split(',').map(header => header.trim());
      setHeaders(headerArray);
  
      const initialRowsData = Array.from({ length: initialRows }, () =>
        headerArray.reduce((acc, header) => {
          acc[header] = '';
          return acc;
        }, {})
      );
      setRows(initialRowsData);
    }, [initialRows, initialHeaderNames]);

  const handleAddRow = () => {
    const newRow = headers.reduce((acc, header) => {
      acc[header] = '';
      return acc;
    }, {});
    setRows([...rows, newRow]);
  };

  const handleDeleteRow = (rowIndex) => {
    const updatedRows = rows.filter((row, index) => index !== rowIndex);
    setRows(updatedRows);
  };

  const handleRowChange = (value, rowIndex, header) => {
    const updatedRows = rows.map((row, index) => {
      if (index === rowIndex) {
        return { ...row, [header]: value };
      }
      return row;
    });
    setRows(updatedRows);
  };

  return (
    <div>
      <TableContainer title={tableTitle}>
        <Table>
          <TableHead>
            <TableRow>
              {headers.map((header, index) => (
                <TableHeader key={index}>{header}</TableHeader>
              ))}
              <TableHeader>Actions</TableHeader>
            </TableRow>
          </TableHead>
          <TableBody>
            {rows.map((row, rowIndex) => (
              <TableRow key={rowIndex}>
                {headers.map((header, cellIndex) => (
                  <TableCell key={cellIndex}>
                    <TextInput
                      id={`row-${rowIndex}-cell-${cellIndex}`}
                      value={row[header]}
                      onChange={(e) => handleRowChange(e.target.value, rowIndex, header)}
                      labelText={header}
                    />
                  </TableCell>
                ))}
                <TableCell>
                  <Button                    
                    onClick={() => handleDeleteRow(rowIndex)}
                    kind="danger"
                  >Delete row</Button>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </TableContainer>

      <div style={{ marginTop: '20px' }}>
        <Button onClick={handleAddRow}>Add Row</Button>
      </div>
    </div>
  );
};

export default DynamicTable;
