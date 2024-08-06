import { useEffect, useState } from "react";
import DynamicTable from './DynamicTable';
import {
    TextInput,
    Dropdown,
    Checkbox,
    Toggle,
    DatePicker,
    DatePickerInput,
    FlexGrid,
    Row,
    Column,
    Heading,
    TextArea,
    Button,
    NumberInput, 
    Link,
    FileUploader,
} from "carbon-components-react";
import { parseISO, format, isValid } from "date-fns";

const componentMapping = {
    "text-input": TextInput,
    dropdown: Dropdown,
    checkbox: Checkbox,
    toggle: Toggle,
    "date-picker": DatePicker,
    "text-area": TextArea,
    "button":Button,
    "number-input":NumberInput,   
    "text-info": Heading,
    "link":Link,
    "file":FileUploader,
    "table":DynamicTable,
};


const ComplexRenderedForm = () => {
    const [formData, setFormData] = useState(
        JSON.parse(window.renderedFormData) || null
    );
    const [state, setState] = useState({});
    const [error, setError] = useState(null);

    const handleInputChange = (event) => {
        const { name, value } = event.target;
        setState((prevState) => ({ ...prevState, [name]: value }));
    };

    const handleToggleChange = (name, checked) => {
        setState((prevState) => ({ ...prevState, [name]: checked }));
    };

    const handleDropdownChange = (name, selectedItem) => {
        setState((prevState) => ({ ...prevState, [name]: selectedItem }));
    };

    const handleOnClick = (event) => {
        const { name, value } = event.target;
        setState((prevState) => ({ ...prevState, [name]: value }));
    };

    const handleDateChange = (name, dates) => {
        const date = dates[0];
        if (isValid(date)) {
            const formattedDate = format(date, "yyyy-MM-dd");
            setState((prevState) => ({ ...prevState, [name]: formattedDate }));
        }
    };

    const handleLinkClick = (event) => {
        const { name, value } = event.target;
        setState((prevState) => ({ ...prevState, [name]: value }));
        event.preventDefault();
        window.open(event.currentTarget.href, '_blank', 'noopener,noreferrer');
    };

    const renderComponent = (item, index) => {
        const Component = componentMapping[item.type];
        if (!Component) return null;

        const codeContext = item.codeContext || {};
        const name = codeContext.name || item.id;

        const toggleLabelId = `toggle-${index}-label`;
        const toggleId = `toggle-${index}`
        switch (item.type) {
            case "text-input":
                return (
                    <Component
                        key={item.id}
                        id={item.id}
                        labelText={item.label}
                        placeholder={item.placeholder}
                        name={name}
                        value={state[name] || ""}
                        onChange={handleInputChange}
                        style={{ marginBottom: "15px" }}
                    />
                );
            case "dropdown":
                const items = state[`${name}Items`] || item.listItems;
                const itemToString = (item) => (item ? item.text : "");
                return (
                    <Component
                        key={item.id}
                        id={name}
                        titleText={item.label}
                        label={item.placeholder}
                        items={items}
                        itemToString={itemToString}
                        initialSelectedItem={state[name]}
                        onChange={(selectedItem) =>
                            handleDropdownChange(
                                name,
                                selectedItem.selectedItem
                            )
                        }
                        style={{ marginBottom: "15px" }}
                    />
                );
            case "checkbox":
                return (
                    <div style={{ marginBottom: "15px" }}>
                        <Component
                            key={item.id}
                            id={item.id}
                            labelText={item.label}
                            name={name}
                            checked={state[name] || false}
                            onChange={(_, { checked }) =>
                                handleInputChange({
                                    target: { name: name, value: checked },
                                })
                            }
                        />
                    </div>
                );
            case "toggle":
                return (
                    <div key={item.id} style={{ marginBottom: "15px" }}>
                        <div id={toggleLabelId}>{item.header}</div>
                        <Component
                            id={toggleId}
                            aria-label={toggleLabelId}
                            labelA={item.offText}
                            labelB={item.onText}
                            size={item.size}
                            toggled={state[name] || false}
                            onToggle={(checked) =>
                                handleToggleChange(name, checked)
                            }
                        />
                    </div>
                );
            case "date-picker":
                const selectedDate = state[name]
                    ? parseISO(state[name])
                    : undefined;
                return (
                    <Component
                        key={item.id}
                        datePickerType="single"
                        value={selectedDate ? [selectedDate] : []}
                        onChange={(dates) => handleDateChange(name, dates)}
                        style={{ marginBottom: "15px" }}
                    >
                        <DatePickerInput
                            id={item.id}
                            placeholder={item.placeholder}
                            labelText={item.labelText}
                            value={
                                selectedDate
                                    ? format(selectedDate, "MM/dd/yyyy")
                                    : ""
                            }
                        />
                    </Component>
                );
            case "text-area":
                return (
                    <Component
                        key={item.id}
                        id={item.id}
                        labelText={item.label}
                        placeholder={item.placeholder}
                        helperText={item.helperText}
                        name={name}
                        value={state[name] || ""}
                        onChange={handleInputChange}
                        rows={4}
                        style={{ marginBottom: "15px" }}
                    />
                );
            case "button":
                return (
                    <Component
                        key={item.id}
                        id={item.id}                            
                        placeholder={item.placeholder}                            
                        name={name}
                        size="md"
                        value={item.label}      
                        onClick={handleOnClick}
                        style={{ marginBottom: "15px" }}                            
                    >
                    {item.label}
                    </Component>
                );  
            case "number-input":
                return (
                    <Component
                        key={item.id}
                        id={item.id}
                        label={item.label}
                        placeholder={item.placeholder}                            
                        name={name}
                        min={0} 
                        max={250}
                        value={item.label}                            
                        style={{ marginBottom: "15px" }}                            
                    >                        
                    </Component>
                ); 
            case "text-info":
                return (
                    <Component
                        key={item.id}
                        id={item.id}                            
                        placeholder={item.placeholder}
                        name={name}
                        value={item.label}                          
                        style={{ marginBottom: item.style.marginBottom , fontSize:item.style.fontSize}}                              
                    >{item.label}
                        </Component>
                );
            case "link":
                return (
                    <Component 
                        id={item.id}  
                        href={item.value} 
                        onClick={handleLinkClick}>
                        {item.label}
                    </Component>
                );  
            case "file":
                return (
                    <div className="cds--file__container">
                    <Component 
                        id={item.id}  
                        labelTitle={item.labelTitle} 
                        labelDescription={item.labelDescription} 
                        buttonLabel={item.buttonLabel}
                        buttonKind={item.buttonKind} 
                        size={item.size} 
                        filenameStatus={item.filenameStatus}  
                        accept={['.jpg', '.png']}
                        multiple={true}
                        disabled={false}
                        iconDescription="Delete file" 
                        name="" 
                    />
                    </div> 
                );                    
            case "table":
                return (
                    <Component
                        id={item.id}
                        tableTitle={item.tableTitle}
                        initialRows={item.initialRows}
                        initialColumns={item.initialColumns}
                        initialHeaderNames={item.initialHeaderNames} />
                );
            
            default:
                return null;
        }
    };

    if (error) {
        return <div>Error loading JSON: {error}</div>;
    }

    if (!formData) {
        return <div>Loading...</div>;
    }

    return (
        <div style={{ maxWidth: "1000px", margin: "0 auto", padding: "20px" }}>
            {formData.ministry_id && <img src={`/images/ministries/${formData.ministry_id}.png`} width="350px" alt="ministry logo" />}
            
            <Heading style={{ marginBottom: "20px", fontSize: "24px" }}>
                {formData.title}
            </Heading>
            <FlexGrid>
                {formData.data.items.map((item, index) => (
                    <Row key={item.id}>
                        <Column>{renderComponent(item, index)}</Column>
                    </Row>
                ))}                
            </FlexGrid>
        </div>
    );
};

export default ComplexRenderedForm;
