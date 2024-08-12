import { useEffect, useState,useRef } from "react";
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
    RadioButton, 
    RadioButtonGroup,
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
    "group":FlexGrid,
    "radio":RadioButtonGroup,
};

const generateUniqueId = (groupId, gpIndex,name) => `grp${groupId}-${gpIndex}-${name}`;

const ComplexRenderedForm = () => {
    const [formData, setFormData] = useState(
        JSON.parse(window.renderedFormData) || null
    );
    const [state, setState] = useState({});
    const [error, setError] = useState(null);
    const [groupStates, setGroupStates] = useState({});
    const [formStates, setFormStates] = useState({});
    const isInitialized = useRef(false);


    useEffect(() => {
        if (!isInitialized.current) {
            // Initialize state for non-grouped fields
            const initialFormStates = {};
            // Initialize state for grouped fields
            const initialGroupStates = {};
            formData.data.items.forEach((item, index) => {
                if (item.type === "group") {
                    initialGroupStates[item.id] = initialGroupStates[item.id] || {};
                    item.groupItems.forEach((groupItem, groupIndex) => {
                        groupItem.fields.forEach((field) => {                            
                            field.id = generateUniqueId(item.id,groupIndex,field.codeContext.name || field.id) || field.id;
                            initialGroupStates[item.id][field.id] = "";
                        });
                    });
                } else {
                    item.id = `${item.codeContext.name || item.id}-${item.id}`;
                    initialFormStates[item.id] = "";
                }
            });
            setFormStates(initialFormStates);
            setGroupStates(initialGroupStates);
            isInitialized.current = true;
        }
    }, [formData]);

    
    const handleInputChange = (fieldId, value, groupId = null) => {
        
        if (groupId) {
            setGroupStates((prevState) => ({
                ...prevState,
                [groupId]: {
                    ...prevState[groupId],
                    [fieldId]: value,
                },
            }));
        } else {
            setFormStates((prevState) => ({
                ...prevState,
                [fieldId]: value,
            }));
        }
    };

    const handleLinkClick = (event) => {
        const { name, value } = event.target;        
        event.preventDefault();
        window.open(event.currentTarget.href, '_blank', 'noopener,noreferrer');
    };
        

    const handleAddGroupItem = (groupId) => {
        
        setFormData((prevState) => {
            const newFormData = { ...prevState };
            const group = newFormData.data.items.find(item => item.id === groupId);
            const newGroupItem = JSON.parse(JSON.stringify(group.groupItems[0])); // Clone the first group item
            
            // Assign unique ids to the new group item fields
            newGroupItem.fields.forEach(field => {                
                const groupIndex =  group.groupItems.length;                
                field.id = generateUniqueId(groupId,groupIndex,field.codeContext.name || field.id);
            });

            group.groupItems.push(newGroupItem);
            return newFormData;
        });

        setGroupStates((prevState) => {
            const newState = { ...prevState };
            const groupState = newState[groupId] || {};
            const newGroupItemState = {};
            const group = formData.data.items.find(item => item.id === groupId);

            // Initialize state for each field in the new group item
            formData.data.items.find(item => item.id === groupId).groupItems[0].fields.forEach(field => {
                const newFieldId = generateUniqueId(groupId,group.groupItems.length-1,field.codeContext.name || field.id);
                newGroupItemState[newFieldId] = "";
            });

            return {
                ...newState,
                [groupId]: {
                    ...groupState,
                    ...newGroupItemState,
                },
            };
        });
    };

    const handleRemoveGroupItem = (groupId, groupItemIndex) => {
        setFormData((prevState) => {
            const newFormData = { ...prevState };
            const group = newFormData.data.items.find(item => item.id === groupId);
            group.groupItems.splice(groupItemIndex, 1);

            // Update IDs for remaining group items
            group.groupItems.forEach((groupItem, newIndex) => {
                groupItem.fields.forEach((field) => {
                    const oldId = field.id;                    
                    field.id =generateUniqueId(groupId,newIndex,field.id.split('-').slice(2))
                    
                    if (newFormData.groupStates && newFormData.groupStates[groupId] && newFormData.groupStates[groupId][oldId]) {
                        newFormData.groupStates[groupId][field.id] = newFormData.groupStates[groupId][oldId];
                        delete newFormData.groupStates[groupId][oldId];
                    }
                });
            });

            return newFormData;
        });

        setGroupStates((prevState) => {
            const newState = { ...prevState };
            const groupState = newState[groupId] || {};

            // Create a new group state excluding the removed item and updating the remaining ones
            const newGroupState = Object.keys(groupState)
                .filter((key) => !key.startsWith(`${groupId}-${groupItemIndex}-`))
                .reduce((acc, key) => {
                    acc[key] = groupState[key];
                    return acc;
                }, {});

            Object.keys(newGroupState).forEach((key) => {
                const [id, idx, ...rest] = key.split('-');
                const index = parseInt(idx, 10);
                if (index > groupItemIndex) {
                    const newKey = [id, index - 1, ...rest].join('-');
                    newGroupState[newKey] = newGroupState[key];
                    delete newGroupState[key];
                }
            });

            return {
                ...newState,
                [groupId]: newGroupState,
            };
        });
    };

    const renderComponent = (item, groupId = null, groupIndex = null) => {
        const Component = componentMapping[item.type];
        if (!Component) return null;

        const codeContext = item.codeContext || {};
        const name = codeContext.name || item.id;
        //const fieldId = groupId ? generateUniqueId(groupId,groupIndex,name) : `${name}-${item.id}`;
        
        const fieldId = item.id;

        switch (item.type) {
            case "text-input":
                return (
                    <Component
                        key={fieldId}
                        id={fieldId}
                        labelText={item.label}
                        placeholder={item.placeholder}
                        name={fieldId}
                        value={groupId ? (groupStates[groupId]?.[fieldId] || "") : (formStates[fieldId] || "")}
                        onChange={(e) => handleInputChange(fieldId, e.target.value, groupId)}
                        style={{ marginBottom: "15px" }}
                    />
                );
            case "dropdown":
                const items = item.listItems.map(({ value, text }) => ({ id: value, label: text }));
                const itemToString = (item) => (item ? item.label : "");
                return (
                    <Component
                        key={fieldId}
                        id={fieldId}
                        titleText={item.label}
                        label={item.placeholder}
                        items={items}
                        itemToString={itemToString}
                        selectedItem={groupId ? groupStates[groupId]?.[fieldId] : formStates[fieldId]}
                        onChange={({ selectedItem }) => handleInputChange(fieldId, selectedItem, groupId)}
                        style={{ marginBottom: "15px" }}
                    />
                );
            case "checkbox":
                return (
                    <div style={{ marginBottom: "15px" }}>
                        <Component
                            key={fieldId}
                            id={fieldId}
                            labelText={item.label}
                            name={fieldId}
                            checked={groupId ? (groupStates[groupId]?.[fieldId] || false) : (formStates[fieldId] || false)}
                            onChange={(_, { checked }) => handleInputChange(fieldId, checked, groupId)}
                        />
                    </div>
                );
            case "toggle":
                return (
                    <div key={fieldId} style={{ marginBottom: "15px" }}>
                        <div id={`${fieldId}-label`}>{item.header}</div>
                        <Component
                            id={fieldId}
                            aria-labelledby={`${fieldId}-label`}
                            labelA={item.offText}
                            labelB={item.onText}
                            size={item.size}
                            toggled={groupId ? (groupStates[groupId]?.[fieldId] || false) : (formStates[fieldId] || false)}
                            onToggle={(checked) => handleInputChange(fieldId, checked, groupId)}
                        />
                    </div>
                );
            case "date-picker":
                const selectedDate = groupId ? (groupStates[groupId]?.[fieldId] ? parseISO(groupStates[groupId][fieldId]) : undefined) : (formStates[fieldId] ? parseISO(formStates[fieldId]) : undefined);
                return (
                    <Component
                        key={fieldId}
                        datePickerType="single"
                        value={selectedDate ? [selectedDate] : []}
                        onChange={(dates) => handleInputChange(fieldId, format(dates[0], "yyyy-MM-dd"), groupId)}
                        style={{ marginBottom: "15px" }}
                    >
                        <DatePickerInput
                            id={fieldId}
                            placeholder={item.placeholder}
                            labelText={item.labelText}
                            value={selectedDate ? format(selectedDate, "MM/dd/yyyy") : ""}
                        />
                    </Component>
                );
            case "text-area":
                return (
                    <Component
                        key={fieldId}
                        id={fieldId}
                        labelText={item.label}
                        placeholder={item.placeholder}
                        helperText={item.helperText}
                        name={fieldId}
                        value={groupId ? (groupStates[groupId]?.[fieldId] || "") : (formStates[fieldId] || "")}
                        onChange={(e) => handleInputChange(fieldId, e.target.value, groupId)}
                        rows={4}
                        style={{ marginBottom: "15px" }}
                    />
                );
            case "button":
                return (
                    <Component
                        key={fieldId}
                        id={fieldId}
                        name={fieldId}
                        size="md"
                        onClick={(e) => handleInputChange(fieldId, e.target.value, groupId)}
                        style={{ marginBottom: "15px" }}
                    >
                        {item.label}
                    </Component>
                );
            case "number-input":
                return (
                    <Component                    
                        helperText={item.helperText}  
                        invalidText="Number is not valid"
                        key={fieldId}
                        id={fieldId}
                        label={item.label}
                        name={fieldId}
                        min={0}
                        max={250}
                        value={groupId ? (groupStates[groupId]?.[fieldId] || 0) : (formStates[fieldId] || 0)}
                        onChange={(e) => handleInputChange(fieldId, e.target.value, groupId)}
                        style={{ marginBottom: "15px" }}
                    />
                );
            case "text-info":
                return (
                    <Component
                        key={fieldId}
                        id={fieldId}
                        style={{ marginBottom: item.style.marginBottom, fontSize: item.style.fontSize }}
                    >
                        {item.label}
                    </Component>
                );
            case "link":
                return (
                    <Component
                        id={fieldId}
                        href={item.value}
                        onClick={handleLinkClick}
                    >
                        {item.label}
                    </Component>
                );
            case "file":
                return (
                    <div className="cds--file__container">
                        <Component
                            id={fieldId}
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
                        id={fieldId}
                        tableTitle={item.tableTitle}
                        initialRows={item.initialRows}
                        initialColumns={item.initialColumns}
                        initialHeaderNames={item.initialHeaderNames}
                    />
                );
            case "radio":
                const radioOptions = item.listItems.map(({ value, text }) => ({ value: value, label: text }));
                return (
                    <Component                
                        legendText= {item.label}
                        id={fieldId}
                        name={fieldId}                        
                        onChange={( value ) => handleInputChange(fieldId, value, groupId)}
                        valueSelected={groupId ? groupStates[groupId]?.[fieldId] : formStates[fieldId]}
                        >
                        {radioOptions.map((option, index) => (
                            <RadioButton
                            key={index}
                            labelText={option.label}
                            value={option.value}
                            id={`${fieldId}-${index}`}
                            />
                        ))}
                    </Component>  
                );     
            case "group":
                return (
                    <div key={item.id} className="group-container">
                        <h2>{item.label}</h2>
                        {item.groupItems.map((groupItem, groupIndex) => (
                            <div key={`${item.id}-${groupIndex}`} className="group-container">
                                {groupItem.fields.map((groupField) => (
                                    <Row key={groupField.id}>
                                        <Column>{renderComponent(groupField, item.id, groupIndex)}</Column>
                                    </Row>
                                ))}
                                {item.groupItems.length > 1 && (
                                    <Button
                                        kind="danger"
                                        onClick={() => handleRemoveGroupItem(item.id, groupIndex)}
                                    >
                                        Remove {item.label}
                                    </Button>
                                )}
                            </div>
                        ))}
                        {item.repeater && (<Button
                            kind="primary"
                            onClick={() => handleAddGroupItem(item.id)}
                        >
                            Add {item.label}
                        </Button>
                        )}
                    </div>
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
            
            <Heading style={{ marginBottom: "20px"}}>
                {formData.title}
            </Heading>
            <FlexGrid>
                {formData.data.items.map((item, index) => (
                    <Row key={item.id}>
                        <Column>{renderComponent(item, item.type === "group" ? item.id : null, index)}</Column>
                    </Row>
                ))}                
            </FlexGrid>
        </div>
    );
};

export default ComplexRenderedForm;